<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\ExceptionReports\Data\ExceptionReportData;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

final class ExceptionReportsHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
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
            $check->mailerConfiguredCheck(),
            $check->fromAddressConfiguredCheck(),
            $check->queueConnectionConfiguredCheck(),
            $check->mailViewCheck(),
            $check->rateLimiterCheck(),
            $check->webhookConfigurationCheck(),
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
        $configured = $this->recipient() !== null;
        $usesLegacyFallback = ! $this->stringConfigIsFilled('capell-exception-reports.recipient')
            && $this->stringConfigIsFilled('services.exception_reports.to');

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.recipient.label'),
            passed: $configured,
            message: match (true) {
                $usesLegacyFallback => (string) __('capell-exception-reports::package.health.recipient.legacy_ready'),
                $configured => (string) __('capell-exception-reports::package.health.recipient.ready'),
                default => (string) __('capell-exception-reports::package.health.recipient.not_ready'),
            },
            remediation: $configured
                ? null
                : (string) __('capell-exception-reports::package.health.recipient.remediation'),
        );
    }

    public function mailerConfiguredCheck(): DoctorCheckResultData
    {
        $ready = app()->bound('mail.manager')
            && $this->configuredMailerExists();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.mailer.label'),
            passed: $ready,
            message: $ready
                ? (string) __('capell-exception-reports::package.health.mailer.ready')
                : (string) __('capell-exception-reports::package.health.mailer.not_ready'),
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.mailer.remediation'),
        );
    }

    public function fromAddressConfiguredCheck(): DoctorCheckResultData
    {
        $ready = $this->configuredFromAddressIsValid();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.from_address.label'),
            passed: $ready,
            message: $ready
                ? (string) __('capell-exception-reports::package.health.from_address.ready')
                : (string) __('capell-exception-reports::package.health.from_address.not_ready'),
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.from_address.remediation'),
        );
    }

    public function queueConnectionConfiguredCheck(): DoctorCheckResultData
    {
        $ready = app()->bound('queue')
            && $this->configuredQueueConnectionExists();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.queue.label'),
            passed: $ready,
            message: $ready
                ? (string) __('capell-exception-reports::package.health.queue.ready')
                : (string) __('capell-exception-reports::package.health.queue.not_ready'),
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.queue.remediation'),
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

    public function webhookConfigurationCheck(): DoctorCheckResultData
    {
        $enabled = (bool) config('capell-exception-reports.webhook.enabled', false);
        $ready = ! $enabled || $this->webhookUrlIsValid();

        return new DoctorCheckResultData(
            label: (string) __('capell-exception-reports::package.health.webhook.label'),
            passed: $ready,
            message: match (true) {
                ! $enabled => (string) __('capell-exception-reports::package.health.webhook.disabled'),
                $ready => (string) __('capell-exception-reports::package.health.webhook.ready'),
                default => (string) __('capell-exception-reports::package.health.webhook.not_ready'),
            },
            remediation: $ready
                ? null
                : (string) __('capell-exception-reports::package.health.webhook.remediation'),
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

    private function configuredMailerExists(): bool
    {
        $mailer = $this->stringConfig('mail.default');

        if ($mailer === null || config(sprintf('mail.mailers.%s', $mailer)) === null) {
            return false;
        }

        try {
            Mail::mailer($mailer);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function configuredFromAddressIsValid(): bool
    {
        $address = $this->stringConfig('mail.from.address');

        return $address !== null && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function configuredQueueConnectionExists(): bool
    {
        $connection = $this->stringConfig('queue.default');

        if ($connection === null || config(sprintf('queue.connections.%s', $connection)) === null) {
            return false;
        }

        try {
            Queue::connection($connection);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function webhookUrlIsValid(): bool
    {
        $url = $this->stringConfig('capell-exception-reports.webhook.url');

        $host = parse_url($url ?? '', PHP_URL_HOST);

        return $url !== null
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string($host)
            && in_array(strtolower($host), $this->webhookAllowedHosts(), true)
            && $this->stringConfig('capell-exception-reports.webhook.signing_secret') !== null;
    }

    /** @return list<string> */
    private function webhookAllowedHosts(): array
    {
        $hosts = config('capell-exception-reports.webhook.allowed_hosts', []);

        return is_array($hosts)
            ? array_values(array_filter(array_map(
                static fn (mixed $host): string => is_string($host) ? strtolower(trim($host)) : '',
                $hosts,
            ), static fn (string $host): bool => $host !== ''))
            : [];
    }

    private function recipient(): ?string
    {
        return $this->stringConfig('capell-exception-reports.recipient')
            ?? $this->stringConfig('services.exception_reports.to');
    }

    private function stringConfigIsFilled(string $key): bool
    {
        return $this->stringConfig($key) !== null;
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function mailViewCanRender(): bool
    {
        try {
            (new UnhandledExceptionReported(ExceptionReportData::from([
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
            ])))->render();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
