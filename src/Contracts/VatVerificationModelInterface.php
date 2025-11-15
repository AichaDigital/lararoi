<?php

namespace Aichadigital\Lararoi\Contracts;

/**
 * Interface for VAT verification model
 *
 * This contract defines the methods that must be implemented
 * by the VAT verification model to allow its use
 * from other packages like Larabill.
 *
 * @property string $vat_code
 * @property string $country_code
 * @property bool $is_valid
 * @property string|null $company_name
 * @property string|null $company_address
 * @property string $api_source
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property array|null $response_data
 */
interface VatVerificationModelInterface
{
    /**
     * Find a verification by VAT code and country
     *
     * @param  string  $vatCode  Complete VAT code (with country prefix)
     * @param  string  $countryCode  Country code
     */
    public static function findByVatCodeAndCountry(string $vatCode, string $countryCode): ?self;

    /**
     * Check if the verification has expired
     */
    public function isExpired(): bool;

    /**
     * Get the complete VAT code
     */
    public function getVatCode(): string;

    /**
     * Get the country code
     */
    public function getCountryCode(): string;

    /**
     * Check if the VAT is valid
     */
    public function isValid(): bool;

    /**
     * Get the company name
     */
    public function getCompanyName(): ?string;

    /**
     * Get the company address
     */
    public function getCompanyAddress(): ?string;

    /**
     * Get the API source used for verification
     */
    public function getApiSource(): string;

    /**
     * Get the verification timestamp
     */
    public function getVerifiedAt(): ?\Illuminate\Support\Carbon;

    /**
     * Get the raw response data
     *
     * @return array<string, mixed>|null
     */
    public function getResponseData(): ?array;
}
