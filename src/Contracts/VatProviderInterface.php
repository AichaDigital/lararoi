<?php

namespace Aichadigital\Lararoi\Contracts;

/**
 * Interface for VAT verification providers
 *
 * Each provider (VIES, vatlayer, viesapi, etc.) must implement this interface
 */
interface VatProviderInterface
{
    /**
     * Verify a VAT number
     *
     * @param  string  $vatNumber  VAT number without country prefix
     * @param  string  $countryCode  Country code (2 letters)
     * @return array{
     *     valid: bool,
     *     name: string|null,
     *     address: string|null,
     *     request_date: string|null,
     *     vat_number: string,
     *     country_code: string,
     *     api_source: string
     * }
     *
     * @throws \Aichadigital\Lararoi\Exceptions\ApiUnavailableException
     */
    public function verify(string $vatNumber, string $countryCode): array;

    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Check if the provider is available
     */
    public function isAvailable(): bool;

    /**
     * Check if the provider is free
     */
    public function isFree(): bool;
}
