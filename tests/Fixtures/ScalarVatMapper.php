<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Tests\Fixtures;

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;

/**
 * A mapper returning a non-array shape, proving map(): mixed is honoured.
 */
class ScalarVatMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return (string) ($canonical['vat_code'] ?? '');
    }
}
