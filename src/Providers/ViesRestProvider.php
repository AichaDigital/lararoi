<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Provider for VIES REST API (Unofficial - Free)
 *
 * REST endpoint not officially documented but functional
 */
class ViesRestProvider implements VatProviderInterface
{
    protected Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$countryCode}/vat/{$vatNumber}";

        try {
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['isValid'])) {
                throw new ApiUnavailableException('VIES_REST', new \Exception('Invalid response format'));
            }

            return [
                'valid' => $data['isValid'] ?? false,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
                'request_date' => $data['requestDate'] ?? null,
                'vat_number' => $data['vatNumber'] ?? $vatNumber,
                'country_code' => $data['countryCode'] ?? strtoupper($countryCode),
                'api_source' => 'VIES_REST',
            ];
        } catch (GuzzleException $e) {
            Log::warning('VIES REST API error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIES_REST', new \Exception($e->getMessage(), $e->getCode(), $e));
        }
    }

    public function getName(): string
    {
        return 'VIES REST';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isFree(): bool
    {
        return true;
    }
}
