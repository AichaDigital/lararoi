<?php

namespace Aichadigital\Lararoi\Contracts;

use Illuminate\Support\Carbon;

/**
 * Interface for the append-only verification tracking model (ADR-002 D3).
 *
 * A swappable model contract in the same spirit as VatVerificationModelInterface:
 * a consumer that needs a different store swaps the model (via
 * `lararoi.models.verification_query`) instead of renaming lararoi's tables.
 *
 * The getters give consumers a stable, model-agnostic read surface over the
 * rows returned by the tracker's consumer-scoped reads.
 *
 * @property string $consumer
 * @property string|null $subject_reference
 * @property string $vat_code
 * @property string $country_code
 * @property bool $is_valid
 * @property string $api_source
 * @property bool $cache_hit
 * @property array<string, mixed>|null $response_snapshot
 * @property Carbon|null $queried_at
 * @property Carbon|null $retention_until
 */
interface VerificationQueryModelInterface
{
    /**
     * Get the consumer key that recorded this verification.
     */
    public function getConsumer(): string;

    /**
     * Get the opaque, consumer-owned subject reference (never interpreted here).
     */
    public function getSubjectReference(): ?string;

    /**
     * Get the complete VAT code (with country prefix).
     */
    public function getVatCode(): string;

    /**
     * Get the country code.
     */
    public function getCountryCode(): string;

    /**
     * Whether the recorded verification was valid.
     */
    public function isValid(): bool;

    /**
     * Get the API source (provider) that answered.
     */
    public function getApiSource(): string;

    /**
     * Whether the recorded verification was served from cache (vs a live provider).
     */
    public function isCacheHit(): bool;

    /**
     * Get the minimized canonical snapshot of the verification facts.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseSnapshot(): ?array;

    /**
     * Get the UTC timestamp the verification was queried at.
     */
    public function getQueriedAt(): ?Carbon;

    /**
     * Get the UTC timestamp after which the row may be pruned (null = keep forever).
     */
    public function getRetentionUntil(): ?Carbon;
}
