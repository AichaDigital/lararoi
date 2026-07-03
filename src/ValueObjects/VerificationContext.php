<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\ValueObjects;

/**
 * Attribution context for a tracked verification (ADR-002 D4).
 *
 * Supplied as the optional third argument to
 * VatVerificationServiceInterface::verifyVatNumber() and as the input to
 * VerificationTrackerInterface::record()/tryRecord(). It carries only the two
 * facts lararoi needs to attribute a verification to a consumer:
 *
 * - `consumer`: the consumer key. When tracking is enabled it MUST be a
 *   registered key in `lararoi.consumers` (D5), otherwise recording throws
 *   UnknownConsumerException.
 * - `subjectReference`: an opaque, consumer-owned entity reference that lararoi
 *   never interprets. The integration guide (D7) requires a stable surrogate id
 *   (the consumer's own entity PK or a hash), never raw PII.
 */
final class VerificationContext
{
    public function __construct(
        public readonly string $consumer,
        public readonly ?string $subjectReference = null,
    ) {}
}
