<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Tests\Fixtures;

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;

/**
 * A tiny consumer mapper: flattens the canonical facts into its own shape.
 *
 * These fixtures live in their own PSR-4 files rather than inside the test
 * because the registry resolves mappers by CLASS-STRING out of config
 * (`config()->set('lararoi.consumers.acme.mapper', AcmeVatMapper::class)`).
 * An anonymous class has no class-string to configure.
 */
class AcmeVatMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return [
            'valid' => (bool) ($canonical['is_valid'] ?? false),
            'id' => $canonical['vat_code'] ?? null,
            'name' => $canonical['company_name'] ?? null,
        ];
    }
}
