<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class ExceptionReportsConsoleCommandsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
