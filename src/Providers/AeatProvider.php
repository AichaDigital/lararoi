<?php

namespace Aichadigital\Lararoi\Providers;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Provider for AEAT Web Service (Official - Free - Requires certificate)
 *
 * Only valid for Spanish NIF
 * Requires digital certificate configuration
 */
class AeatProvider implements VatProviderInterface
{
    protected ?string $certPath;

    protected ?string $keyPath;

    protected ?string $p12Path;

    protected string $endpoint;

    protected ?string $passphrase;

    public function __construct(
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $p12Path = null,
        ?string $endpoint = null,
        ?string $passphrase = null
    ) {
        $config = \config('lararoi.aeat', []);
        $this->certPath = $certPath ?? $config['cert_path'] ?? null;
        $this->keyPath = $keyPath ?? $config['key_path'] ?? null;
        $this->p12Path = $p12Path ?? $config['p12_path'] ?? null;
        $this->endpoint = $endpoint ?? $config['endpoint'] ??
            'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP';
        $this->passphrase = $passphrase ?? $config['passphrase'] ?? null;
    }

    public function verify(string $vatNumber, string $countryCode): array
    {
        // AEAT only works for Spain
        if (strtoupper($countryCode) !== 'ES') {
            throw new ApiUnavailableException('AEAT', new \Exception('AEAT only supports Spanish NIF'));
        }

        if (! $this->isAvailable()) {
            throw new ApiUnavailableException('AEAT', new \Exception('AEAT certificate not configured'));
        }

        $wsdl = $this->endpoint.'?wsdl';
        $timeout = config('lararoi.timeout', 15);

        try {
            $options = [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => $timeout,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                    ],
                ]),
            ];

            // Configurar certificado
            // Option 1: PKCS#12 certificate (.p12 or .pfx) - most common
            if ($this->p12Path && file_exists($this->p12Path)) {
                $options['local_cert'] = $this->p12Path;
                if ($this->passphrase) {
                    $options['passphrase'] = $this->passphrase;
                }
            }
            // Option 2: Separate certificate and key (.crt/.pem + .key)
            elseif ($this->certPath && $this->keyPath && file_exists($this->certPath) && file_exists($this->keyPath)) {
                // For separate certificates, we need to combine them or use stream context
                $options['local_cert'] = $this->certPath;
                if ($this->passphrase) {
                    $options['passphrase'] = $this->passphrase;
                }
                // Configure private key in stream context
                $options['stream_context'] = stream_context_create([
                    'ssl' => [
                        'local_cert' => $this->certPath,
                        'local_pk' => $this->keyPath,
                        'passphrase' => $this->passphrase,
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                    ],
                ]);
            }

            $client = new SoapClient($wsdl, $options);

            // AEAT requires NIF and Name. If we don't have name, we try with NIF only
            // Note: AEAT may return error if name is missing, but some cases work
            $contribuyenteData = [
                'Nif' => $vatNumber,
            ];

            // If we have name in VAT number or can extract it, add it
            // For now, we try without name first

            $result = $client->verificar([
                'Contribuyente' => $contribuyenteData,
            ]);

            // Adapt AEAT response to standard format
            $contribuyente = is_array($result->Contribuyente)
                ? $result->Contribuyente[0]
                : $result->Contribuyente;

            $isValid = isset($contribuyente->Resultado)
                && $contribuyente->Resultado === 'IDENTIFICADO';

            return [
                'valid' => $isValid,
                'name' => $contribuyente->Nombre ?? null,
                'address' => null, // AEAT does not return address in this endpoint
                'request_date' => null,
                'vat_number' => $contribuyente->Nif ?? $vatNumber,
                'country_code' => 'ES',
                'api_source' => 'AEAT',
            ];
        } catch (SoapFault $e) {
            Log::warning('AEAT SOAP error', [
                'vat' => $vatNumber,
                'fault_code' => $e->getCode(),
                'fault_string' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('AEAT', new \Exception($e->getMessage(), $e->getCode(), $e));
        } catch (\Exception $e) {
            Log::error('AEAT unexpected error', [
                'vat' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            throw new ApiUnavailableException('AEAT', $e);
        }
    }

    public function getName(): string
    {
        return 'AEAT';
    }

    public function isAvailable(): bool
    {
        if (! extension_loaded('soap')) {
            return false;
        }

        // Verificar si hay certificado PKCS#12
        if ($this->p12Path && file_exists($this->p12Path)) {
            return true;
        }

        // Verificar si hay certificado y clave separados
        if ($this->certPath && $this->keyPath
            && file_exists($this->certPath) && file_exists($this->keyPath)) {
            return true;
        }

        return false;
    }

    public function isFree(): bool
    {
        return true;
    }
}
