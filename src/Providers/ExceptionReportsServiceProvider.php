<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Providers;

use Capell\Core\Enums\ExtensionStatusEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Extensions\ExtensionLifecycleRepository;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Capell\ExceptionReports\Support\InactivePostmarkRecipientFailure;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Override;
use Spatie\LaravelPackageTools\Package;
use Throwable;
use WeakMap;

final class ExceptionReportsServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-exception-reports';

    public static string $packageName = 'capell-app/exception-reports';

    /** @var WeakMap<Handler, true> */
    private WeakMap $reporterHandlers;

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasMigrations([
                '2026_08_22_000001_create_exception_reports_table',
                '2026_09_29_120000_remove_implicit_timestamp_updates_from_exception_reports',
            ]);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(ExceptionReportMailSanitizer::class);
        $this->reporterHandlers ??= new WeakMap;

        $this->callAfterResolving(
            ExceptionHandlerContract::class,
            fn (ExceptionHandlerContract $handler): null => $this->registerExceptionReporter($handler),
        );
    }

    // Enrol for late enable; handler observation must start before installation and provider boot.
    #[Override]
    protected function bootInstalledRuntime(): void {}

    private function registerExceptionReporter(ExceptionHandlerContract $handler): null
    {
        if (! $handler instanceof Handler || isset($this->reporterHandlers[$handler])) {
            return null;
        }

        // Transient bindings can trigger callAfterResolving twice for the same handler.
        $this->reporterHandlers[$handler] = true;

        $handler->reportable(function (Throwable $exception): ?bool {
            if (InactivePostmarkRecipientFailure::matches($exception)) {
                InactivePostmarkRecipientFailure::logNoticeIfEnabled();

                return false;
            }

            if (! config('capell-exception-reports.enabled', true) || $this->packageDisabled()) {
                return null;
            }

            try {
                ReportExceptionByEmailAction::run($exception);
            } catch (Throwable $throwable) {
                $this->logReporterFailure($throwable, $exception);
            }

            return null;
        });

        return null;
    }

    // Not-yet-installed packages keep reporting so installation failures still reach operators.
    private function packageDisabled(): bool
    {
        $name = self::$packageName;

        try {
            if (CapellCore::isPackageEnabled($name) || ! CapellCore::hasPackage($name)) {
                return false;
            }

            $status = resolve(ExtensionLifecycleRepository::class)->status($name, collect([$name => CapellCore::getPackage($name)]));
        } catch (Throwable) {
            // An unreadable package state must not hide the exception being reported.
            return false;
        }

        return $status === ExtensionStatusEnum::Disabled;
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
