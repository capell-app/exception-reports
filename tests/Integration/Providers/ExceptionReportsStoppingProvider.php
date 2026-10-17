<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Tests\Integration\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;
use Override;
use RuntimeException;
use Throwable;

final class ExceptionReportsStoppingProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->afterResolving(ExceptionHandler::class, static function (ExceptionHandler $handler): void {
            throw_unless($handler instanceof Handler, RuntimeException::class);
            $handler->reportable(static fn (Throwable $exception): bool => false);
        });
    }
}
