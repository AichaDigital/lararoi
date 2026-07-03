<?php

namespace Aichadigital\Lararoi\Contracts;

use Aichadigital\Lararoi\Exceptions\TrackingDisabledException;
use Aichadigital\Lararoi\Exceptions\UnknownConsumerException;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;
use Carbon\CarbonInterface;

/**
 * Multi-consumer tracking / audit contract (ADR-002 D4).
 *
 * Records real verification results only (R1): a verifyVatNumber() return is the
 * input, never a hand-built payload — cache_hit is derived from that result's
 * cache_status/cached and the snapshot is minimized to the seven canonical
 * verification facts. It is not a channel for asserting an unverified status.
 *
 * All reads are consumer-scoped: no read returns another consumer's history.
 */
interface VerificationTrackerInterface
{
    /**
     * Record a real verification result as a new append-only row.
     *
     * @param  array<string, mixed>  $verificationResult  the verifyVatNumber() return;
     *                                                    cache_hit is derived from its
     *                                                    cache_status, snapshot from its facts
     *
     * @throws TrackingDisabledException when tracking is disabled
     * @throws UnknownConsumerException when $context->consumer is not registered
     */
    public function record(VerificationContext $context, array $verificationResult): VerificationQueryModelInterface;

    /**
     * Best-effort variant: returns null instead of throwing when tracking is disabled.
     *
     * Still throws UnknownConsumerException when tracking is enabled and the
     * consumer is not registered — a typo is a programmer error, not silence.
     *
     * @param  array<string, mixed>  $verificationResult  the verifyVatNumber() return
     *
     * @throws UnknownConsumerException when $context->consumer is not registered
     */
    public function tryRecord(VerificationContext $context, array $verificationResult): ?VerificationQueryModelInterface;

    /**
     * Read a consumer's history for one opaque subject reference.
     *
     * @return iterable<VerificationQueryModelInterface>
     */
    public function forSubject(string $consumer, string $subjectReference): iterable;

    /**
     * Read a consumer's history for one VAT code + country.
     *
     * @return iterable<VerificationQueryModelInterface>
     */
    public function forVat(string $consumer, string $vatCode, string $countryCode): iterable;

    /**
     * Read a consumer's history within a queried_at window.
     *
     * @return iterable<VerificationQueryModelInterface>
     */
    public function between(string $consumer, CarbonInterface $from, CarbonInterface $to): iterable;
}
