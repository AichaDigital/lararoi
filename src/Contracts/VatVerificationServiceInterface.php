<?php

namespace Aichadigital\Lararoi\Contracts;

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
     * @param  string  $vatNumber  VAT number without country prefix
     * @param  string  $countryCode  Country code (2 letters, e.g.: ES, FR, DE)
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
     * @throws \Aichadigital\Lararoi\Exceptions\VatVerificationException
     */
    public function verifyVatNumber(string $vatNumber, string $countryCode): array;
}
