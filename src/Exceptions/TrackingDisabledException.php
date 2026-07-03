<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Exceptions;

use RuntimeException;

/**
 * Thrown by VerificationTrackerInterface::record() when tracking is disabled
 * (ADR-002 D4).
 *
 * An explicit record() must never no-op silently — silence is opt-in via
 * tryRecord(), which returns null instead of throwing when disabled.
 */
class TrackingDisabledException extends RuntimeException
{
    public function __construct(
        string $message = 'Verification tracking is disabled (lararoi.tracking.enabled = false). '
            .'Enable it to record verifications, or use tryRecord() for a best-effort no-op.'
    ) {
        parent::__construct($message);
    }
}
