<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider for isvat.eu (Free with limit of 100 queries/month)
 */
class IsvatProvider implements VatProviderInterface
{
    protected bool $useLive;

    protected int $timeout;

    public function __construct(bool $useLive = false, ?int $timeout = null)
    {
        $this->useLive = $useLive;
        $this->timeout = $timeout ?? config('lararoi.timeout', 15);
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        $endpoint = $this->useLive ? 'live' : '';
        $url = "https://www.isvat.eu/{$endpoint}/{$countryCode}/{$vatNumber}";

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url);

            // ISVAT returns 404 with {"valid":false} for invalid VAT numbers
            // This is a valid response, not an error
            if ($response->status() === 404) {
                $data = $response->json();

                if (is_array($data) && isset($data['valid'])) {
                    return $this->buildResponse($data, $vatNumber, $countryCode);
                }

                // 404 without valid JSON structure - this is an error
                Log::warning('ISVAT API 404 error without valid structure', [
                    'country' => $countryCode,
                    'vat' => $vatNumber,
                    'body' => $response->body(),
                ]);

                throw new ApiUnavailableException('ISVAT', new \Exception('404 Not Found without valid response structure', 404));
            }

            // Throw exception for server errors (5xx)
            $response->throw();

            $data = $response->json();

            return $this->buildResponse($data, $vatNumber, $countryCode);
        } catch (RequestException $e) {
            Log::warning('ISVAT API request error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
                'status' => $e->response->status(),
            ]);

            throw new ApiUnavailableException('ISVAT', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (ConnectionException $e) {
            Log::warning('ISVAT API connection error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('ISVAT', new \Exception($e->getMessage(), 0, $e));
        }
    }

    /**
     * Build standardized response array from ISVAT API data
     */
    protected function buildResponse(array $data, string $vatNumber, string $countryCode): array
    {
        return [
            'valid' => $data['valid'] ?? false,
            'name' => $this->normalizeValue($data['name'] ?? null),
            'address' => $this->normalizeValue($data['address'] ?? null),
            'request_date' => null,
            'vat_number' => $data['vatNumber'] ?? $vatNumber,
            'country_code' => $data['countryCode'] ?? strtoupper($countryCode),
            'api_source' => 'ISVAT',
        ];
    }

    /**
     * Normalize ISVAT API response values
     *
     * ISVAT API returns name and address as arrays with numeric keys (e.g., {"0": "value"})
     * This method extracts the first value from such arrays or returns the value as-is
     */
    protected function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            // ISVAT returns {"0": "actual value"} format
            return $value[0] ?? $value['0'] ?? null;
        }

        return (string) $value;
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
