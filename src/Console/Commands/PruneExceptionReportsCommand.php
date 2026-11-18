<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Console\Commands;

use Capell\ExceptionReports\Models\ExceptionReport;
use Illuminate\Console\Command;

final class PruneExceptionReportsCommand extends Command
{
    protected $signature = 'capell:exception-reports-prune';

    protected $description = 'Delete persisted exception reports older than the configured retention window.';

    public function handle(): int
    {
        // Deletes in chunks of 1000, so a large backlog never runs as one long delete.
        $deleted = (new ExceptionReport)->pruneAll();

        $this->components->info(sprintf('Pruned %d exception report%s.', $deleted, $deleted === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
