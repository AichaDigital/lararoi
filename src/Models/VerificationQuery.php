<?php

namespace Aichadigital\Lararoi\Models;

use Aichadigital\Lararoi\Contracts\VerificationQueryModelInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only tracking / audit model (ADR-002 D3).
 *
 * Each recorded verification is a NEW row — the model is never updated in place
 * and carries no SoftDeletes (expiry is a physical prune, D5). Contrast with
 * VatVerification, which is the "one current row per NIF" cache.
 */
class VerificationQuery extends Model implements VerificationQueryModelInterface
{
    protected $table = 'roi_verification_queries';

    protected $fillable = [
        'consumer',
        'subject_reference',
        'vat_code',
        'country_code',
        'is_valid',
        'api_source',
        'cache_hit',
        'response_snapshot',
        'queried_at',
        'retention_until',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'cache_hit' => 'boolean',
        'response_snapshot' => 'array',
        'queried_at' => 'datetime',
        'retention_until' => 'datetime',
    ];

    /**
     * {@inheritDoc}
     */
    public function getConsumer(): string
    {
        return $this->consumer ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getSubjectReference(): ?string
    {
        return $this->subject_reference;
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
    public function getApiSource(): string
    {
        return $this->api_source ?? 'UNKNOWN';
    }

    /**
     * {@inheritDoc}
     */
    public function isCacheHit(): bool
    {
        return $this->cache_hit ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function getResponseSnapshot(): ?array
    {
        return $this->response_snapshot;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueriedAt(): ?Carbon
    {
        return $this->queried_at;
    }

    /**
     * {@inheritDoc}
     */
    public function getRetentionUntil(): ?Carbon
    {
        return $this->retention_until;
    }
}
