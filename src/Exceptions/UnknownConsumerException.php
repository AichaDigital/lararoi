<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Exceptions;

use RuntimeException;

/**
 * Thrown when a verification is recorded for a consumer that is not registered
 * in the `lararoi.consumers` allow-list while tracking is enabled (ADR-002 D5).
 *
 * This closes the typo hole — a mistyped consumer key fails loudly instead of
 * silently accumulating unpolicied, never-pruned history — and forces every
 * real consumer to exist in config (with an explicit retention_days) before it
 * can write.
 */
class UnknownConsumerException extends RuntimeException
{
    protected string $consumer;

    public function __construct(string $consumer)
    {
        $this->consumer = $consumer;

        parent::__construct(sprintf(
            'Consumer "%s" is not registered in lararoi.consumers. Register it with an '
            .'explicit retention_days before recording verifications.',
            $consumer
        ));
    }

    /**
     * Get the unregistered consumer key that triggered the exception.
     */
    public function getConsumer(): string
    {
        return $this->consumer;
    }
}
