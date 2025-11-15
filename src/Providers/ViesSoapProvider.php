<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Provider for VIES SOAP API (Official - Free)
 *
 * Official service from the European Commission
 */
class ViesSoapProvider implements VatProviderInterface
{
    protected bool $testMode;

    public function __construct(bool $testMode = false)
    {
        $this->testMode = $testMode;
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        $wsdl = $this->testMode
            ? 'https://ec.europa.eu/taxation_customs/vies/checkVatTestService.wsdl'
            : 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

        try {
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
            ]);

            $result = $client->checkVat([
                'countryCode' => strtoupper($countryCode),
                'vatNumber' => $vatNumber,
            ]);

            return [
                'valid' => $result->valid ?? false,
                'name' => $result->name ?? null,
                'address' => $result->address ?? null,
                'request_date' => $result->requestDate ?? null,
                'vat_number' => $result->vatNumber ?? $vatNumber,
                'country_code' => $result->countryCode ?? strtoupper($countryCode),
                'api_source' => 'VIES_SOAP',
            ];
        } catch (SoapFault $e) {
            Log::warning('VIES SOAP error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'fault_code' => $e->getCode(),
                'fault_string' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIES_SOAP', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (\Exception $e) {
            Log::error('VIES SOAP unexpected error', [
                'country' => $countryCode,
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('VIES_SOAP', $e);
        }
    }

    public function getName(): string
    {
        return 'VIES SOAP';
    }

    public function isAvailable(): bool
    {
        return extension_loaded('soap');
    }

    public function isFree(): bool
    {
        return true;
    }
}
