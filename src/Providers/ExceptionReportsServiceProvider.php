<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Providers;

use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Spatie\LaravelPackageTools\Package;
use Throwable;

final class ExceptionReportsServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-exception-reports';

    public static string $packageName = 'capell-app/exception-reports';

    private bool $reporterRegistered = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ExceptionReportMailSanitizer::class);

        $this->app->afterResolving(
            ExceptionHandlerContract::class,
            fn (ExceptionHandlerContract $handler): null => $this->registerExceptionReporter($handler),
        );

        if ($this->app->resolved(ExceptionHandlerContract::class)) {
            $this->registerExceptionReporter($this->app->make(ExceptionHandlerContract::class));
        }
    }

    private function registerExceptionReporter(ExceptionHandlerContract $handler): null
    {
        if ($this->reporterRegistered || ! $handler instanceof Handler) {
            return null;
        }

        $this->reporterRegistered = true;

        $handler->reportable(function (Throwable $exception): void {
            if (! config('capell-exception-reports.enabled', true)) {
                return;
            }

            try {
                resolve(ReportExceptionByEmailAction::class)->handle($exception);
            } catch (Throwable) {
                //
            }
        });

        return null;
    }
}
