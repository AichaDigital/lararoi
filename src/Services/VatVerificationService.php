<?php

namespace Aichadigital\Lararoi\Services;

use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Exceptions\VatVerificationException;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Support\VatFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Main VAT/NIF-IVA verification service
 *
 * Implements business logic for verifying VAT numbers:
 * - Cache
 * - Fallback between providers (similar to Larabill)
 * - Database persistence
 */
class VatVerificationService implements VatVerificationServiceInterface
{
    protected VatProviderManager $providerManager;

    protected ?VatVerificationModelInterface $model = null;

    protected array $config;

    public function __construct(?VatProviderManager $providerManager = null)
    {
        $this->providerManager = $providerManager ?? app(VatProviderManager::class);
        $this->config = function_exists('config') ? config('lararoi', []) : [];

        // Set model from config with backward compatibility
        $modelConfig = $this->config['models']['vat_verification'] ?? VatVerification::class;

        // Support both old format (string) and new format (array with 'class' key)
        $modelClass = is_array($modelConfig)
            ? ($modelConfig['class'] ?? VatVerification::class)
            : $modelConfig;

        if (class_exists($modelClass)) {
            $this->model = app($modelClass);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function verifyVatNumber(string $vatNumber, string $countryCode): array
    {
        // Normalize inputs: upper-case, trim, strip spaces and dashes.
        $vatNumber = strtoupper(preg_replace('/[\s\-]/', '', trim($vatNumber)) ?? '');
        $countryCode = strtoupper(trim($countryCode));

        // Required arguments.
        if ($vatNumber === '' || $countryCode === '') {
            throw new VatVerificationException(
                'VAT number and country code are required',
                'INVALID_INPUT'
            );
        }

        $vatCode = $countryCode.$vatNumber;

        // Syntactic short-circuit: reject obviously malformed VAT numbers for
        // known countries without hitting any provider.
        if (! VatFormat::isValid($countryCode, $vatNumber)) {
            return $this->formatResponse([
                'is_valid' => false,
                'vat_code' => $vatCode,
                'country_code' => $countryCode,
                'company_name' => null,
                'company_address' => null,
                'api_source' => 'LOCAL_VALIDATION',
                'request_date' => null,
            ], 'fresh');
        }

        $cacheEnabled = $this->config['cache']['enabled'] ?? true;

        // If cache is disabled, skip to direct verification (most agnostic mode)
        if (! $cacheEnabled) {
            return $this->verifyDirectly($vatCode, $vatNumber, $countryCode);
        }

        // 2. Check in-memory cache (Laravel Cache)
        $cacheKey = $this->getCacheKey($vatCode);
        $cached = Cache::get($cacheKey);

        if ($cached !== null && ! $this->isCacheExpired($cached)) {
            Log::debug('VAT verification from cache', [
                'vat_code' => $vatCode,
                'cached_at' => $cached['cached_at'] ?? null,
            ]);

            return $this->formatResponse($cached, 'cached');
        }

        // 3. Check database
        if ($this->model) {
            $verification = $this->model::findByVatCodeAndCountry($vatCode, $countryCode);

            if ($verification && ! $verification->isExpired()) {
                $data = [
                    'is_valid' => $verification->isValid(),
                    'vat_code' => $verification->getVatCode(),
                    'country_code' => $verification->getCountryCode(),
                    'company_name' => $verification->getCompanyName(),
                    'company_address' => $verification->getCompanyAddress(),
                    'api_source' => $verification->getApiSource(),
                    'request_date' => $verification->getVerifiedAt()?->toIso8601String(),
                ];

                // Update memory cache
                $this->updateCache($cacheKey, $data);

                return $this->formatResponse($data, 'cached');
            }

            // If we found expired data in database, we'll refresh it
            $isRefresh = $verification !== null;
        }

        // 4. Verify via providers (with automatic fallback)
        try {
            $result = $this->normalizeProviderResult(
                $this->providerManager->verify($vatNumber, $countryCode)
            );

            // 5. Persist to database
            if ($this->model) {
                $this->persistVerification($vatCode, $countryCode, $result);
            }

            // 6. Update cache
            $this->updateCache($cacheKey, $result);

            // Determine cache status: 'refreshed' if updating expired cache, 'fresh' if new
            $cacheStatus = isset($isRefresh) && $isRefresh ? 'refreshed' : 'fresh';

            return $this->formatResponse($result, $cacheStatus);
        } catch (ApiUnavailableException $e) {
            // If all providers fail, return error
            Log::error('All VAT verification providers failed', [
                'vat_code' => $vatCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify VAT number directly without using cache (most agnostic mode)
     */
    protected function verifyDirectly(string $vatCode, string $vatNumber, string $countryCode): array
    {
        try {
            $result = $this->normalizeProviderResult(
                $this->providerManager->verify($vatNumber, $countryCode)
            );

            return $this->formatResponse($result, 'fresh');
        } catch (ApiUnavailableException $e) {
            Log::error('VAT verification failed (cache disabled)', [
                'vat_code' => $vatCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Persist verification to database.
     *
     * A single atomic upsert keyed on the UNIQUE (vat_code, country_code) index
     * (ADR-002 D1/D2): concurrent verifications of the same NIF converge on one
     * row instead of racing a read-then-write to insert duplicates. The row is
     * built through the model so casts apply (response_data serialized as JSON,
     * verified_at formatted as a datetime), then handed to the upsert already
     * serialized — Eloquent's upsert() does not apply casts itself.
     *
     * @param  array<string, mixed>  $result  Canonical verification data
     */
    protected function persistVerification(string $vatCode, string $countryCode, array $result): void
    {
        if (! $this->model) {
            return;
        }

        try {
            $row = new ($this->model::class)();

            // The interface does not guarantee Eloquent, but every shipped/swapped
            // model is an Eloquent Model; upsert() lives on the query builder.
            if (! $row instanceof Model) {
                return;
            }

            $row->forceFill([
                'vat_code' => $vatCode,
                'country_code' => $countryCode,
                'is_valid' => $result['is_valid'] ?? false,
                'company_name' => $result['company_name'] ?? null,
                'company_address' => $result['company_address'] ?? null,
                'api_source' => $result['api_source'] ?? 'UNKNOWN',
                'verified_at' => \now(),
                'response_data' => $result,
            ]);

            $row->newQuery()->upsert(
                [$row->getAttributes()],
                ['vat_code', 'country_code'],
                ['is_valid', 'company_name', 'company_address', 'api_source', 'verified_at', 'response_data'],
            );
        } catch (\Exception $e) {
            Log::warning('Failed to persist VAT verification', [
                'vat_code' => $vatCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize a provider result (provider vocabulary) into the canonical
     * internal shape used by caching, persistence and the output formatter.
     *
     * Providers speak {valid, name, address, vat_number, ...}; the rest of the
     * service speaks {is_valid, company_name, company_address, vat_code, ...}.
     * This is the single translation point between the two vocabularies.
     *
     * @param  array<string, mixed>  $providerResult
     * @return array<string, mixed>
     */
    protected function normalizeProviderResult(array $providerResult): array
    {
        $countryCode = (string) ($providerResult['country_code'] ?? '');
        $vatNumber = (string) ($providerResult['vat_number'] ?? '');

        return [
            'is_valid' => (bool) ($providerResult['valid'] ?? false),
            'vat_code' => $countryCode.$vatNumber,
            'country_code' => $countryCode,
            'company_name' => $providerResult['name'] ?? null,
            'company_address' => $providerResult['address'] ?? null,
            'api_source' => $providerResult['api_source'] ?? 'UNKNOWN',
            'request_date' => $providerResult['request_date'] ?? null,
        ];
    }

    /**
     * Format the canonical response shape.
     *
     * Input is always the canonical vocabulary (provider results are
     * normalized via normalizeProviderResult before reaching this point).
     *
     * @param  array<string, mixed>  $data  Canonical verification data
     * @param  string  $cacheStatus  One of: 'fresh', 'cached', 'refreshed'
     * @return array<string, mixed>
     */
    protected function formatResponse(array $data, string $cacheStatus): array
    {
        return [
            'is_valid' => $data['is_valid'] ?? false,
            'vat_code' => $data['vat_code'] ?? '',
            'country_code' => $data['country_code'] ?? '',
            'company_name' => $data['company_name'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'api_source' => $data['api_source'] ?? 'UNKNOWN',
            'cached' => $cacheStatus === 'cached', // Backward-compatible boolean flag
            'cache_status' => $cacheStatus, // 'fresh', 'cached', or 'refreshed'
            'request_date' => $data['request_date'] ?? null,
            'response_data' => $data,
        ];
    }

    /**
     * Get cache key
     */
    protected function getCacheKey(string $vatCode): string
    {
        return 'lararoi:vat:'.md5($vatCode);
    }

    /**
     * Check if cache has expired
     */
    protected function isCacheExpired(array $cached): bool
    {
        $ttl = $this->config['cache']['ttl'] ?? 86400;
        $cachedAt = $cached['cached_at'] ?? 0;

        return time() - $cachedAt > $ttl;
    }

    /**
     * Update cache
     */
    protected function updateCache(string $key, array $data): void
    {
        $ttl = $this->config['cache']['ttl'] ?? 86400;
        $data['cached_at'] = time();

        Cache::put($key, $data, $ttl);
    }

    /**
     * Set model for persistence
     */
    public function setModel(VatVerificationModelInterface $model): void
    {
        $this->model = $model;
    }
}
