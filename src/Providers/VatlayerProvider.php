<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Provider for vatlayer.com (Paid - 100 queries/month free)
 */
class VatlayerProvider implements VatProviderInterface
{
    protected Client $httpClient;

    protected ?string $apiKey;

    public function __construct(?Client $httpClient = null, ?string $apiKey = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
        $config = \config('lararoi.provider_config.vatlayer', []);
        $this->apiKey = $apiKey ?? $config['api_key'] ?? null;
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        if (! $this->isAvailable()) {
            throw new ApiUnavailableException('VATLAYER', new \Exception('API key not configured'));
        }

        $url = 'http://apilayer.net/api/validate';
        $params = [
            'access_key' => $this->apiKey,
            'vat_number' => strtoupper($countryCode).$vatNumber,
        ];

        try {
            $response = $this->httpClient->get($url, ['query' => $params]);
            $data = json_decode($response->getBody()->getContents(), true);

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
        } catch (GuzzleException $e) {
            Log::warning('VATLAYER API error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VATLAYER', new \Exception($e->getMessage(), $e->getCode(), $e));
        }
    }

    public function getName(): string
    {
        return 'VATLAYER';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function isFree(): bool
    {
        return false; // Has free limit but is a paid service
    }
}
