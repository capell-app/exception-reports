<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

final class ExceptionReportsHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->providerLoadedCheck(),
            $check->recipientConfiguredCheck(),
            $check->mailViewCheck(),
            $check->rateLimiterCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function providerLoadedCheck(): DoctorCheckResultData
    {
        $loaded = app()->getProvider(ExceptionReportsServiceProvider::class) instanceof ExceptionReportsServiceProvider;

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.provider.label'),
            passed: $loaded,
            message: $loaded
                ? (string) __('capell-exception-reports::package.health.provider.ready')
                : (string) __('capell-exception-reports::package.health.provider.not_ready'),
            remediation: $loaded
                ? null
                : (string) __('capell-exception-reports::package.health.provider.remediation'),
        );
    }

    public function recipientConfiguredCheck(): DoctorCheckResultData
    {
        $recipient = config('capell-exception-reports.recipient');
        $configured = is_string($recipient) && $recipient !== '';

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.recipient.label'),
            passed: $configured,
            message: $configured
                ? (string) __('capell-exception-reports::package.health.recipient.ready')
                : (string) __('capell-exception-reports::package.health.recipient.not_ready'),
            remediation: $configured
                ? null
                : (string) __('capell-exception-reports::package.health.recipient.remediation'),
        );
    }

    public function mailViewCheck(): DoctorCheckResultData
    {
        $ready = class_exists(UnhandledExceptionReported::class)
            && app()->bound(ExceptionReportMailSanitizer::class)
            && $this->mailViewCanRender();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.mail.label'),
            passed: $ready,
            message: $ready
                ? (string) __('capell-exception-reports::package.health.mail.ready')
                : (string) __('capell-exception-reports::package.health.mail.not_ready'),
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.mail.remediation'),
        );
    }

    public function rateLimiterCheck(): DoctorCheckResultData
    {
        $ready = app()->bound('cache')
            && app()->bound(ExceptionHandlerContract::class)
            && $this->handlerCanRegisterReportables();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.rate_limiter.label'),
            passed: $ready,
            message: $ready
                ? (string) __('capell-exception-reports::package.health.rate_limiter.ready')
                : (string) __('capell-exception-reports::package.health.rate_limiter.not_ready'),
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.rate_limiter.remediation'),
        );
    }

    private function handlerCanRegisterReportables(): bool
    {
        try {
            $handler = resolve(ExceptionHandlerContract::class);

            return method_exists($handler, 'reportable');
        } catch (Throwable) {
            return false;
        }
    }

    private function mailViewCanRender(): bool
    {
        try {
            (new UnhandledExceptionReported([
                'subject' => 'Exception reported',
                'source' => 'health-check',
                'summary' => [
                    'app' => 'Capell',
                    'environment' => 'testing',
                    'exception' => RuntimeException::class,
                    'message' => 'Health check',
                    'file' => 'HealthCheck.php',
                    'line' => 1,
                    'reported_at' => now()->toDayDateTimeString(),
                ],
                'request' => [
                    'route_parameters' => [],
                ],
                'user' => [],
                'trace' => '',
            ]))->render();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
