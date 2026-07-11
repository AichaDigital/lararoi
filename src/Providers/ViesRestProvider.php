<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Support\VatFormat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider for VIES REST API (Unofficial - Free)
 *
 * REST endpoint not officially documented but functional
 */
class ViesRestProvider implements VatProviderInterface
{
    protected int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? config('lararoi.timeout', 15);
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        // Encode the input-derived segments so path metacharacters (/, ?, #, …)
        // cannot alter the request path — the host is fixed, this hardens the path.
        $url = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/'
            .rawurlencode($countryCode).'/vat/'.rawurlencode($vatNumber);

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url);

            $response->throw();

            $data = $response->json();

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
        } catch (RequestException $e) {
            Log::warning('VIES REST API request error', [
                'country' => $countryCode,
                'vat' => VatFormat::mask($vatNumber),
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIES_REST', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (ConnectionException $e) {
            Log::warning('VIES REST API connection error', [
                'country' => $countryCode,
                'vat' => VatFormat::mask($vatNumber),
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIES_REST', new \Exception($e->getMessage(), 0, $e));
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
