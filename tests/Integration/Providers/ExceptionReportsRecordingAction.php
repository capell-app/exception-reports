<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Tests\Integration\Providers;

use Throwable;

final class ExceptionReportsRecordingAction
{
    /** @var list<Throwable> */
    public array $exceptions = [];

    public function handle(Throwable $exception): void
    {
        $this->exceptions[] = $exception;
    }
}
