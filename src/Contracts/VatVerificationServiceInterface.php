<?php

namespace Aichadigital\Lararoi\Contracts;

use Aichadigital\Lararoi\Exceptions\UnknownConsumerException;
use Aichadigital\Lararoi\Exceptions\VatVerificationException;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;

/**
 * Interface for VAT/NIF-IVA verification service
 *
 * This contract defines the public interface that must be implemented
 * by any VAT number verification service.
 */
interface VatVerificationServiceInterface
{
    /**
     * Verify a VAT/NIF-IVA number
     *
     * The canonical 2-argument call returns the stable canonical shape and
     * tracks nothing. Supplying a VerificationContext opts into the passive
     * (inline) tracking path (ADR-002 D4): when tracking is enabled, one
     * append-only row is recorded after the result is resolved; when tracking
     * is disabled or no context is given, nothing is written. An enabled but
     * unregistered consumer still throws UnknownConsumerException (D5).
     *
     * @param  string  $vatNumber  VAT number without country prefix
     * @param  string  $countryCode  Country code (2 letters, e.g.: ES, FR, DE)
     * @param  VerificationContext|null  $context  Optional consumer attribution for tracking
     * @return array{
     *     is_valid: bool,
     *     vat_code: string,
     *     country_code: string,
     *     company_name: string|null,
     *     company_address: string|null,
     *     api_source: string,
     *     cached: bool,
     *     request_date: string|null,
     *     response_data?: array
     * }
     *
     * @throws VatVerificationException
     * @throws UnknownConsumerException when tracking is enabled and the context's consumer is not registered
     */
    public function verifyVatNumber(
        string $vatNumber,
        string $countryCode,
        ?VerificationContext $context = null,
    ): array;
}
