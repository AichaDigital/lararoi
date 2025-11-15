<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Provider for isvat.eu (Free with limit of 100 queries/month)
 */
class IsvatProvider implements VatProviderInterface
{
    protected Client $httpClient;

    protected bool $useLive;

    public function __construct(?Client $httpClient = null, bool $useLive = false)
    {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
        $this->useLive = $useLive;
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        $endpoint = $this->useLive ? 'live' : '';
        $url = "https://www.isvat.eu/{$endpoint}/{$countryCode}/{$vatNumber}";

        try {
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'valid' => $data['valid'] ?? false,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
                'request_date' => null,
                'vat_number' => $data['vatNumber'] ?? $vatNumber,
                'country_code' => $data['countryCode'] ?? strtoupper($countryCode),
                'api_source' => 'ISVAT',
            ];
        } catch (GuzzleException $e) {
            Log::warning('ISVAT API error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('ISVAT', new \Exception($e->getMessage(), $e->getCode(), $e));
        }
    }

    public function getName(): string
    {
        return 'ISVAT';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isFree(): bool
    {
        return true; // Free with limit
    }
}
