<?php

namespace Aichadigital\Lararoi\Support;

use Illuminate\Support\Str;

/**
 * Best-effort syntactic validation of intra-community VAT numbers.
 *
 * Validates the *format* (length and character classes) of a VAT number for a
 * given EU member-state country code, following the canonical VIES patterns.
 * It deliberately does NOT perform checksum validation — the goal is to reject
 * obviously malformed input before hitting a provider, never to reject a
 * syntactically plausible number.
 *
 * Country codes that are not covered are treated permissively (isValid returns
 * true) so unknown or future codes always fall through to the live provider.
 *
 * The VAT number passed here must already be normalized: upper-cased and
 * stripped of spaces/dashes, without the country prefix.
 */
final class VatFormat
{
    /**
     * Canonical per-country VAT format patterns (country prefix excluded).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'AT' => '/^U[A-Z0-9]{8}$/',
        'BE' => '/^[01][0-9]{9}$/',
        'BG' => '/^[0-9]{9,10}$/',
        'CY' => '/^[0-9]{8}[A-Z]$/',
        'CZ' => '/^[0-9]{8,10}$/',
        'DE' => '/^[0-9]{9}$/',
        'DK' => '/^[0-9]{8}$/',
        'EE' => '/^[0-9]{9}$/',
        'EL' => '/^[0-9]{9}$/',
        'ES' => '/^[A-Z0-9][0-9]{7}[A-Z0-9]$/',
        'FI' => '/^[0-9]{8}$/',
        'FR' => '/^[A-Z0-9]{2}[0-9]{9}$/',
        'HR' => '/^[0-9]{11}$/',
        'HU' => '/^[0-9]{8}$/',
        'IE' => '/^[A-Z0-9]{8,9}$/',
        'IT' => '/^[0-9]{11}$/',
        'LT' => '/^([0-9]{9}|[0-9]{12})$/',
        'LU' => '/^[0-9]{8}$/',
        'LV' => '/^[0-9]{11}$/',
        'MT' => '/^[0-9]{8}$/',
        'NL' => '/^[0-9]{9}B[0-9]{2}$/',
        'PL' => '/^[0-9]{10}$/',
        'PT' => '/^[0-9]{9}$/',
        'RO' => '/^[0-9]{2,10}$/',
        'SE' => '/^[0-9]{12}$/',
        'SI' => '/^[0-9]{8}$/',
        'SK' => '/^[0-9]{10}$/',
    ];

    /**
     * Whether a country code has a known VAT format pattern.
     */
    public static function isKnownCountry(string $countryCode): bool
    {
        return isset(self::PATTERNS[strtoupper($countryCode)]);
    }

    /**
     * Validate the syntactic format of a VAT number for its country.
     *
     * Returns true for unknown country codes (permissive fall-through).
     */
    public static function isValid(string $countryCode, string $vatNumber): bool
    {
        $pattern = self::PATTERNS[strtoupper($countryCode)] ?? null;

        if ($pattern === null) {
            return true;
        }

        return preg_match($pattern, strtoupper($vatNumber)) === 1;
    }

    /**
     * Mask a VAT number (or VAT code) for safe logging.
     *
     * Keeps only the first and last two characters and replaces the middle
     * with asterisks, preserving length. Values of four characters or fewer
     * are fully masked so nothing useful about a short identifier leaks. The
     * goal is log hygiene for a fiscal identifier (potential PII under GDPR),
     * not cryptographic protection.
     */
    public static function mask(string $value): string
    {
        $length = strlen($value);

        // Short values reveal too much if partially shown: mask them whole.
        if ($length <= 4) {
            return Str::mask($value, '*', 0);
        }

        // Keep the first and last two characters, mask the middle.
        return Str::mask($value, '*', 2, $length - 4);
    }
}
