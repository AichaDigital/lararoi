<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Console\Commands;

use Aichadigital\Lararoi\Services\VerificationTracker;
use Illuminate\Console\Command;

/**
 * Prune expired verification-query rows (ADR-002 D5).
 *
 * Deletes only rows whose retention_until is set AND has passed (compared in
 * UTC). Never touches null-retention ("keep forever") rows — operators of
 * keep-forever consumers own that growth.
 */
class PruneVerificationQueriesCommand extends Command
{
    protected $signature = 'roi:prune-verification-queries';

    protected $description = 'Delete expired verification-query rows whose retention_until has passed (UTC).';

    public function handle(VerificationTracker $tracker): int
    {
        $deleted = $tracker->prune();

        $this->info(sprintf('Pruned %d expired verification query row(s).', $deleted));

        return self::SUCCESS;
    }
}
