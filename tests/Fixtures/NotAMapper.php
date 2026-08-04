<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Tests\Fixtures;

/**
 * A class that is NOT a mapper — used for the invalid-config guard.
 *
 * Its whole purpose is to have a class-string that can be put in config so the
 * registry's is_a() check has something valid to reject, which is why it cannot
 * be anonymous.
 */
class NotAMapper {}
