<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider for viesapi.eu (Paid - Free test plan)
 */
class ViesApiProvider implements VatProviderInterface
{
    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected ?string $ip;

    protected int $timeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?string $ip = null,
        ?int $timeout = null
    ) {
        $config = \config('lararoi.provider_config.viesapi', []);
        $this->apiKey = $apiKey ?? $config['api_key'] ?? null;
        $this->apiSecret = $apiSecret ?? $config['api_secret'] ?? null;
        $this->ip = $ip ?? $config['ip'] ?? null;
        $this->timeout = $timeout ?? config('lararoi.timeout', 15);
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        if (! $this->isAvailable()) {
            throw new ApiUnavailableException('VIESAPI', new \Exception('API key not configured'));
        }

        // ViesAPI expects the VAT with country code as single parameter
        $fullVat = strtoupper($countryCode).$vatNumber;

        // Use test endpoint if no secret is configured
        $endpoint = ($this->apiSecret === null || $this->apiSecret === '') ? 'api-test' : 'api';
        $url = "https://viesapi.eu/{$endpoint}/get/vies/euvat/{$fullVat}";

        // Build HTTP request with Laravel Http facade
        $request = Http::timeout($this->timeout)
            ->acceptJson();

        // Add Basic Authentication if we have both API key and secret
        if ($this->apiKey && $this->apiSecret) {
            $request = $request->withBasicAuth($this->apiKey, $this->apiSecret);
        }

        // Add IP headers if configured
        if ($this->ip) {
            $request = $request->withHeaders([
                'X-Forwarded-For' => $this->ip,
                'X-Real-IP' => $this->ip,
            ]);
        }

        try {
            $response = $request->get($url);

            // Handle error responses (4xx, 5xx)
            if ($response->failed()) {
                $data = $response->json();

                if (is_array($data)) {
                    // Check if it's a successful response with valid/invalid VAT
                    if (isset($data['valid'])) {
                        return $this->buildResponse($data, $vatNumber, $countryCode);
                    }

                    // Check for error response
                    if (isset($data['error']) && isset($data['error']['code'])) {
                        $errorCode = $data['error']['code'];
                        $errorDesc = $data['error']['description'] ?? 'Unknown error';

                        // Codes 22 and 205 mean invalid VAT number (not an API error)
                        if (in_array($errorCode, [22, 205])) {
                            return [
                                'valid' => false,
                                'name' => null,
                                'address' => null,
                                'request_date' => null,
                                'vat_number' => $vatNumber,
                                'country_code' => strtoupper($countryCode),
                                'api_source' => 'VIESAPI',
                            ];
                        }

                        // Other error codes are real API errors
                        throw new ApiUnavailableException(
                            'VIESAPI',
                            new \Exception("VIESAPI error {$errorCode}: {$errorDesc}", $errorCode)
                        );
                    }
                }

                Log::warning('VIESAPI HTTP error', [
                    'country' => $countryCode,
                    'vat' => $vatNumber,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new ApiUnavailableException('VIESAPI', new \Exception('HTTP '.$response->status().' error', $response->status()));
            }

            $data = $response->json();

            return $this->buildResponse($data, $vatNumber, $countryCode);
        } catch (RequestException $e) {
            Log::warning('VIESAPI request error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIESAPI', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (ConnectionException $e) {
            Log::warning('VIESAPI connection error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIESAPI', new \Exception($e->getMessage(), 0, $e));
        }
    }

    /**
     * Build standardized response array from VIESAPI data
     */
    protected function buildResponse(array $data, string $vatNumber, string $countryCode): array
    {
        return [
            'valid' => $data['valid'] ?? false,
            'name' => $data['traderName'] ?? $data['name'] ?? null,
            'address' => $data['traderAddress'] ?? $data['address'] ?? null,
            'request_date' => $data['requestDate'] ?? null,
            'vat_number' => $data['vatNumber'] ?? $vatNumber,
            'country_code' => $data['countryCode'] ?? strtoupper($countryCode),
            'api_source' => 'VIESAPI',
        ];
    }

    public function getName(): string
    {
        return 'VIESAPI';
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function isFree(): bool
    {
        return false; // Has free test plan but is a paid service
    }
}
