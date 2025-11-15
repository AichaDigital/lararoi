<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Provider for viesapi.eu (Paid - Free test plan)
 */
class ViesApiProvider implements VatProviderInterface
{
    protected Client $httpClient;

    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected ?string $ip;

    public function __construct(
        ?Client $httpClient = null,
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?string $ip = null
    ) {
        $config = \config('lararoi.provider_config.viesapi', []);
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
        $this->apiKey = $apiKey ?? $config['api_key'] ?? null;
        $this->apiSecret = $apiSecret ?? $config['api_secret'] ?? null;
        $this->ip = $ip ?? $config['ip'] ?? null;
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        if (! $this->isAvailable()) {
            throw new ApiUnavailableException('VIESAPI', new \Exception('API key not configured'));
        }

        $url = "https://viesapi.eu/api/check/{$this->apiKey}/{$countryCode}/{$vatNumber}";

        $options = [];

        // If IP is configured, it may be for headers or specific configuration
        // viesapi.eu may require specific headers according to their documentation
        if ($this->ip) {
            $options['headers'] = [
                'X-Forwarded-For' => $this->ip,
                'X-Real-IP' => $this->ip,
            ];
        }

        try {
            $response = $this->httpClient->get($url, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'valid' => $data['valid'] ?? false,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
                'request_date' => null,
                'vat_number' => $data['vatNumber'] ?? $vatNumber,
                'country_code' => $data['countryCode'] ?? strtoupper($countryCode),
                'api_source' => 'VIESAPI',
            ];
        } catch (GuzzleException $e) {
            Log::warning('VIESAPI error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIESAPI', new \Exception($e->getMessage(), $e->getCode(), $e));
        }
    }

    public function getName(): string
    {
        return 'VIESAPI';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function isFree(): bool
    {
        return false; // Has free test plan but is a paid service
    }
}
