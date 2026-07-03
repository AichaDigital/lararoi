<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Mappers;

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;

/**
 * Default output mapper (ADR-002 D6): returns the canonical result unchanged.
 *
 * This is what a consumer with no declared `mapper` gets — lararoi's stable
 * canonical seven-field array passes through untouched. It is bound as the
 * default VerificationResultMapperInterface and is the fallback the registry
 * returns for any consumer without a configured or registered mapper.
 */
final class IdentityVerificationResultMapper implements VerificationResultMapperInterface
{
    /**
     * {@inheritDoc}
     */
    public function map(array $canonical): mixed
    {
        return $canonical;
    }
}
