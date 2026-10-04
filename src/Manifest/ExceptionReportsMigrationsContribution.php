<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsExtensionMigration;
use Override;

final class ExceptionReportsMigrationsContribution implements ExtensionContribution, RunsExtensionMigration
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
