<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider for vatlayer.com (Paid - 100 queries/month free)
 */
class VatlayerProvider implements VatProviderInterface
{
    protected ?string $apiKey;

    protected int $timeout;

    public function __construct(?string $apiKey = null, ?int $timeout = null)
    {
        $config = \config('lararoi.provider_config.vatlayer', []);
        $this->apiKey = $apiKey ?? $config['api_key'] ?? null;
        $this->timeout = $timeout ?? config('lararoi.timeout', 15);
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        if (! $this->isAvailable()) {
            throw new ApiUnavailableException('VATLAYER', new \Exception('API key not configured'));
        }

        $url = 'http://apilayer.net/api/validate';

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, [
                    'access_key' => $this->apiKey,
                    'vat_number' => strtoupper($countryCode).$vatNumber,
                ]);

            $response->throw();

            $data = $response->json();

            if (isset($data['error'])) {
                throw new ApiUnavailableException('VATLAYER', new \Exception($data['error']['info'] ?? 'API error'));
            }

            return [
                'valid' => $data['valid'] ?? false,
                'name' => $data['company_name'] ?? null,
                'address' => $data['company_address'] ?? null,
                'request_date' => null,
                'vat_number' => $data['vat_number'] ?? $vatNumber,
                'country_code' => $data['country_code'] ?? strtoupper($countryCode),
                'api_source' => 'VATLAYER',
            ];
        } catch (RequestException $e) {
            Log::warning('VATLAYER API request error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VATLAYER', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (ConnectionException $e) {
            Log::warning('VATLAYER API connection error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VATLAYER', new \Exception($e->getMessage(), 0, $e));
        }
    }

    public function getName(): string
    {
        return 'VATLAYER';
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function isFree(): bool
    {
        return false; // Has free limit but is a paid service
    }
}
