<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class ReportExceptionByEmailAction
{
    use AsObject;

    public function handle(Throwable $exception): void
    {
        $recipient = $this->recipient();
        $webhookUrl = $this->webhookUrl();

        if ($recipient === null && $webhookUrl === null) {
            return;
        }

        try {
            if (! $this->canReport($exception)) {
                if ($recipient !== null) {
                    QueueRateLimitedExceptionDigestAction::run(
                        $exception,
                        $recipient,
                        $this->buildReport($exception, $this->currentRequest()),
                    );
                }

                return;
            }

            $report = resolve(ExceptionReportMailSanitizer::class)->sanitize(
                $this->buildReport($exception, $this->currentRequest()),
            );

            if ($recipient !== null) {
                Mail::to($recipient)->queue(new UnhandledExceptionReported($report));
            }

            if ($webhookUrl !== null) {
                SendExceptionReportWebhookAction::run($webhookUrl, $report, $exception);
            }
        } catch (Throwable $reporterFailure) {
            $this->logReporterFailure($reporterFailure, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(Throwable $exception, ?Request $request): array
    {
        $route = $request?->route();
        $route = $route instanceof Route ? $route : null;

        $source = $this->source($exception, $request, $route);

        return [
            'subject' => $this->subject($exception, $source),
            'source' => $source,
            'summary' => [
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500, '...'),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'reported_at' => now()->toDayDateTimeString(),
            ],
            'request' => [
                'method' => $request?->method(),
                'path' => $request?->path(),
                'route_name' => $route?->getName(),
                'request_id' => $request?->headers->get('x-request-id') ?? $request?->headers->get('x-correlation-id'),
            ],
            'console' => $this->consoleContext(),
            'user' => null,
            'trace' => (bool) config('capell-exception-reports.include_trace', false) ? $exception->getTraceAsString() : null,
        ];
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function source(Throwable $exception, ?Request $request, ?Route $route): string
    {
        if (is_string($route?->getName()) && $route->getName() !== '') {
            return 'route: ' . $route->getName();
        }

        if (is_string($route?->getActionName()) && $route->getActionName() !== 'Closure') {
            return 'action: ' . $route->getActionName();
        }

        if ($request instanceof Request && $request->path() !== '/') {
            return 'path: ' . $request->path();
        }

        $commandName = $this->consoleCommandName();

        if ($commandName !== null) {
            return 'command: ' . $commandName;
        }

        return 'file: ' . basename($exception->getFile()) . ':' . $exception->getLine();
    }

    private function subject(Throwable $exception, string $source): string
    {
        return Str::limit(
            '[' . $this->appName() . '] ' . $exception::class . ' in ' . $source,
            180,
            '...',
        );
    }

    private function canReport(Throwable $exception): bool
    {
        $signatureKey = 'exception-report-email:signature:' . $this->signature($exception);
        $globalKey = 'exception-report-email:global';

        $signatureAttempts = $this->positiveIntegerConfig('capell-exception-reports.rate_limits.signature_attempts', 1);
        $globalAttempts = $this->positiveIntegerConfig('capell-exception-reports.rate_limits.global_attempts', 10);

        if (RateLimiter::tooManyAttempts($signatureKey, $signatureAttempts) || RateLimiter::tooManyAttempts($globalKey, $globalAttempts)) {
            return false;
        }

        RateLimiter::hit($signatureKey, $this->positiveIntegerConfig('capell-exception-reports.rate_limits.signature_decay_seconds', 60 * 15));
        RateLimiter::hit($globalKey, $this->positiveIntegerConfig('capell-exception-reports.rate_limits.global_decay_seconds', 60 * 60));

        return true;
    }

    private function signature(Throwable $exception): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ]));
    }

    private function recipient(): ?string
    {
        $recipient = config('capell-exception-reports.recipient');

        if (! is_string($recipient) || $recipient === '') {
            $legacyRecipient = config('services.exception_reports.to');
            $recipient = is_string($legacyRecipient) ? $legacyRecipient : null;
        }

        return is_string($recipient) && $recipient !== '' ? $recipient : null;
    }

    private function webhookUrl(): ?string
    {
        if (! (bool) config('capell-exception-reports.webhook.enabled', false)) {
            return null;
        }

        $url = config('capell-exception-reports.webhook.url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function appName(): string
    {
        $appName = config('app.name');

        return is_scalar($appName) ? (string) $appName : 'Laravel';
    }

    private function positiveIntegerConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (is_int($value)) {
            return max(1, $value);
        }

        if (is_string($value) && is_numeric($value)) {
            return max(1, (int) $value);
        }

        return max(1, $default);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consoleContext(): ?array
    {
        if (! app()->runningInConsole()) {
            return null;
        }

        $argv = $this->argv();

        return ['command' => $this->consoleCommandName($argv)];
    }

    /**
     * @param  array<int, string>|null  $argv
     */
    private function consoleCommandName(?array $argv = null): ?string
    {
        $argv ??= $this->argv();

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '' || str_starts_with($argument, '-')) {
                continue;
            }

            return $argument;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function argv(): array
    {
        $argv = $_SERVER['argv'] ?? [];

        if (! is_array($argv)) {
            return [];
        }

        return array_values(array_filter($argv, is_string(...)));
    }

    private function logReporterFailure(Throwable $reporterFailure, Throwable $originalException): void
    {
        try {
            Log::warning(
                'Exception Reports failed to queue an exception email.',
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
