<?php

namespace Aichadigital\Lararoi\Models;

use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model for storing VAT/NIF-IVA verifications
 *
 * This model persists verifications to
 * avoid repeated API calls and improve performance.
 */
class VatVerification extends Model implements VatVerificationModelInterface
{
    use SoftDeletes;

    protected $table = 'vat_verifications';

    protected $fillable = [
        'vat_code',
        'country_code',
        'is_valid',
        'company_name',
        'company_address',
        'api_source',
        'verified_at',
        'response_data',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'verified_at' => 'datetime',
        'response_data' => 'array',
    ];

    /**
     * {@inheritDoc}
     */
    public static function findByVatCodeAndCountry(string $vatCode, string $countryCode): ?self
    {
        return static::where('vat_code', $vatCode)
            ->where('country_code', strtoupper($countryCode))
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function isExpired(): bool
    {
        if (! $this->verified_at) {
            return true;
        }

        $ttl = config('lararoi.cache_ttl', 86400); // 24 hours by default
        $expiresAt = $this->verified_at->addSeconds($ttl);

        return now()->isAfter($expiresAt);
    }

    /**
     * {@inheritDoc}
     */
    public function getVatCode(): string
    {
        return $this->vat_code ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getCountryCode(): string
    {
        return $this->country_code ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(): bool
    {
        return $this->is_valid ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function getCompanyName(): ?string
    {
        return $this->company_name;
    }

    /**
     * {@inheritDoc}
     */
    public function getCompanyAddress(): ?string
    {
        return $this->company_address;
    }

    /**
     * {@inheritDoc}
     */
    public function getApiSource(): string
    {
        return $this->api_source ?? 'UNKNOWN';
    }

    /**
     * {@inheritDoc}
     */
    public function getVerifiedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->verified_at;
    }

    /**
     * {@inheritDoc}
     */
    public function getResponseData(): ?array
    {
        return $this->response_data;
    }

    /**
     * Scope to search by VAT code
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByVatCode($query, string $vatCode)
    {
        return $query->where('vat_code', $vatCode);
    }

    /**
     * Scope to search by country
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope for valid verifications
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope for expired verifications
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        $ttl = config('lararoi.cache_ttl', 86400);
        $expirationDate = now()->subSeconds($ttl);

        return $query->where('verified_at', '<', $expirationDate);
    }
}
