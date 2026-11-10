<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/capell-exception-reports.php', 'capell-exception-reports');

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/exception-reports', __DIR__ . '/../../database/migrations', [
                '2026_08_22_000001_create_exception_reports_table' => Config::string('capell-exception-reports.persistence.table', 'exception_reports'),
            ]);
        });
    }
}
