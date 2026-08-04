<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Tests\Fixtures;

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;

/**
 * A class that IS a mapper by type (so the class-string is_a() check passes) but
 * is rebound in the container to a non-mapper instance — used to exercise the
 * defensive runtime instanceof guard that fires after container resolution.
 *
 * Both halves of that trick need the name: the class-string goes into config and
 * the same name is the container binding key.
 */
class ContainerReboundMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return $canonical;
    }
}
