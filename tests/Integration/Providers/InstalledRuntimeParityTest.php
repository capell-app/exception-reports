<?php

declare(strict_types=1);

use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Capell\Tests\Support\InstalledRuntimeParity;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('exception-reports', static function (Application $app): void {
        $reporters = static function (ExceptionHandler $handler): array {
            throw_unless($handler instanceof Handler, RuntimeException::class);
            $callbacks = new ReflectionProperty(Handler::class, 'reportCallbacks')->getValue($handler);
            throw_unless(is_array($callbacks), RuntimeException::class);

            return array_filter($callbacks, static function (mixed $callback): bool {
                throw_unless(is_object($callback), RuntimeException::class);
                $report = new ReflectionProperty($callback, 'callback')->getValue($callback);

                return $report instanceof Closure
                    && new ReflectionFunction($report)->getClosureScopeClass()?->getName() === ExceptionReportsServiceProvider::class;
            });
        };
        $handler = $app->make(ExceptionHandler::class);
        expect($reporters($handler))->toHaveCount(1);

        $binding = $app->getBindings()[ExceptionHandler::class];
        try {
            $app->bind(ExceptionHandler::class, static fn (): Handler => new Handler($app));
            $replacement = $app->make(ExceptionHandler::class);
            expect($reporters($replacement))->toHaveCount(1);
        } finally {
            $app->bind(ExceptionHandler::class, $binding['concrete'], $binding['shared']);
            $app->instance(ExceptionHandler::class, $handler);
        }
    });
});
