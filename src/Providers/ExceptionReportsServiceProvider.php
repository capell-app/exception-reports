<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Providers;

use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Capell\ExceptionReports\Support\InactivePostmarkRecipientFailure;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $handler->reportable(function (Throwable $exception): ?bool {
            if (InactivePostmarkRecipientFailure::matches($exception)) {
                InactivePostmarkRecipientFailure::logNoticeIfEnabled();

                return false;
            }

            if (! config('capell-exception-reports.enabled', true)) {
                return null;
            }

            try {
                ReportExceptionByEmailAction::run($exception);
            } catch (Throwable $reporterFailure) {
                $this->logReporterFailure($reporterFailure, $exception);
            }

            return null;
        });

        return null;
    }

    private function logReporterFailure(Throwable $reporterFailure, Throwable $originalException): void
    {
        try {
            Log::warning(
                'Exception Reports failed to run the exception reporter.',
                resolve(ExceptionReportMailSanitizer::class)->sanitizeLogContext([
                    'reporter_exception' => $reporterFailure::class,
                    'reporter_message' => Str::limit($reporterFailure->getMessage(), 500, '...'),
                    'original_exception' => $originalException::class,
                    'original_message' => Str::limit($originalException->getMessage(), 500, '...'),
                    'original_file' => $originalException->getFile(),
                    'original_line' => $originalException->getLine(),
                ]),
            );
        } catch (Throwable) {
            //
        }
    }
}
