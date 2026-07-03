<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Services;

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;
use Aichadigital\Lararoi\Mappers\IdentityVerificationResultMapper;
use RuntimeException;

/**
 * Per-consumer resolver for output mappers (ADR-002 D6).
 *
 * The mapper is a separate, consumer-invoked transform — it is NEVER applied
 * inside verifyVatNumber(), which keeps returning the canonical array. A
 * consumer that wants its own shape resolves mapperFor($consumer) at its own
 * boundary and calls ->map($canonicalArray).
 *
 * Resolution order for a consumer:
 *   1. a programmatically registered instance (register()) — takes precedence;
 *   2. the `lararoi.consumers.<consumer>.mapper` class string, resolved from
 *      the container (must implement VerificationResultMapperInterface);
 *   3. the identity mapper (returns the canonical array unchanged).
 *
 * A `mapper` key set to something that is not a valid mapper class is a config
 * error and throws — the same fail-loud spirit as the tracker's model guard.
 */
class VerificationResultMapperRegistry
{
    protected VerificationResultMapperInterface $default;

    /**
     * Programmatically registered mappers, keyed by consumer.
     *
     * @var array<string, VerificationResultMapperInterface>
     */
    protected array $registered = [];

    public function __construct(?VerificationResultMapperInterface $default = null)
    {
        $this->default = $default ?? new IdentityVerificationResultMapper;
    }

    /**
     * Register a mapper instance for a consumer programmatically.
     *
     * Takes precedence over the consumer's `mapper` config key. Useful when a
     * consumer's mapper carries runtime state that a class-string config cannot
     * express.
     */
    public function register(string $consumer, VerificationResultMapperInterface $mapper): void
    {
        $this->registered[$consumer] = $mapper;
    }

    /**
     * Resolve the output mapper for a consumer (ADR-002 D6).
     *
     * @throws RuntimeException when the consumer's `mapper` config is set but is
     *                          not a class implementing VerificationResultMapperInterface
     */
    public function mapperFor(string $consumer): VerificationResultMapperInterface
    {
        if (isset($this->registered[$consumer])) {
            return $this->registered[$consumer];
        }

        $mapperClass = config("lararoi.consumers.{$consumer}.mapper");

        if ($mapperClass === null) {
            return $this->default;
        }

        if (! is_string($mapperClass) || ! is_a($mapperClass, VerificationResultMapperInterface::class, true)) {
            throw new RuntimeException(sprintf(
                'The lararoi mapper configured for consumer "%s" must be a class implementing %s.',
                $consumer,
                VerificationResultMapperInterface::class,
            ));
        }

        $mapper = app($mapperClass);

        // is_a(..., true) validated the class-string; the runtime instanceof
        // keeps the narrowing honest for PHPStan (level 5 rejects a @var here)
        // and guards a container binding that returns the wrong instance.
        if (! $mapper instanceof VerificationResultMapperInterface) {
            throw new RuntimeException(sprintf(
                'The lararoi mapper configured for consumer "%s" did not resolve to %s.',
                $consumer,
                VerificationResultMapperInterface::class,
            ));
        }

        return $mapper;
    }
}
