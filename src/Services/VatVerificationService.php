<?php

namespace Aichadigital\Lararoi\Services;

use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
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

        // Set model from config
        $modelClass = $this->config['models']['vat_verification'] ?? \Aichadigital\Lararoi\Models\VatVerification::class;
        if (class_exists($modelClass)) {
            $this->model = app($modelClass);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function verifyVatNumber(string $vatNumber, string $countryCode): array
    {
        // Normalize inputs
        $vatNumber = strtoupper(trim($vatNumber));
        $countryCode = strtoupper(trim($countryCode));
        $vatCode = $countryCode.$vatNumber;

        // 2. Check in-memory cache (Laravel Cache)
        $cacheKey = $this->getCacheKey($vatCode);
        $cached = Cache::get($cacheKey);

        if ($cached !== null && ! $this->isCacheExpired($cached)) {
            Log::debug('VAT verification from cache', [
                'vat_code' => $vatCode,
                'cached_at' => $cached['cached_at'] ?? null,
            ]);

            return $this->formatResponse($cached, true);
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

                // Update cache
                $this->updateCache($cacheKey, $data);

                return $this->formatResponse($data, true);
            }
        }

        // 4. Verify via providers (with automatic fallback)
        try {
            $result = $this->providerManager->verify($vatNumber, $countryCode);

            // 5. Persist to database
            if ($this->model) {
                $this->persistVerification($vatCode, $countryCode, $result);
            }

            // 6. Update cache
            $this->updateCache($cacheKey, $result);

            return $this->formatResponse($result, false);
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
     * Persist verification to database
     */
    protected function persistVerification(string $vatCode, string $countryCode, array $result): void
    {
        if (! $this->model) {
            return;
        }

        try {
            $verification = $this->model::findByVatCodeAndCountry($vatCode, $countryCode);

            if (! $verification) {
                /** @var \Aichadigital\Lararoi\Models\VatVerification $verification */
                $verification = new ($this->model::class)();
                $verification->vat_code = $vatCode;
                $verification->country_code = $countryCode;
            }

            /** @var \Aichadigital\Lararoi\Models\VatVerification $verification */
            $verification->is_valid = $result['valid'] ?? false;
            $verification->company_name = $result['name'] ?? null;
            $verification->company_address = $result['address'] ?? null;
            $verification->api_source = $result['api_source'] ?? 'UNKNOWN';
            $verification->verified_at = \now();
            $verification->response_data = $result;

            $verification->save();
        } catch (\Exception $e) {
            Log::warning('Failed to persist VAT verification', [
                'vat_code' => $vatCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format response according to contract
     */
    protected function formatResponse(array $data, bool $cached): array
    {
        return [
            'is_valid' => $data['valid'] ?? $data['is_valid'] ?? false,
            'vat_code' => ($data['country_code'] ?? '').($data['vat_number'] ?? ''),
            'country_code' => $data['country_code'] ?? '',
            'company_name' => $data['name'] ?? $data['company_name'] ?? null,
            'company_address' => $data['address'] ?? $data['company_address'] ?? null,
            'api_source' => $data['api_source'] ?? 'UNKNOWN',
            'cached' => $cached,
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
        $ttl = $this->config['cache_ttl'] ?? 86400; // 24 hours by default
        $cachedAt = $cached['cached_at'] ?? 0;

        return (time() - $cachedAt) > $ttl;
    }

    /**
     * Update cache
     */
    protected function updateCache(string $key, array $data): void
    {
        $ttl = $this->config['cache_ttl'] ?? 86400;
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
