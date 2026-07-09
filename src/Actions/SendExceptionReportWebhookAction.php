<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class SendExceptionReportWebhookAction
{
    use AsObject;

    /**
     * @param  array<string, mixed>  $report
     */
    public function handle(string $url, array $report, Throwable $exception): void
    {
        try {
            $payload = [
                'event' => 'exception.reported',
                'package' => 'capell-app/exception-reports',
                'subject' => $report['subject'] ?? null,
                'report' => resolve(ExceptionReportMailSanitizer::class)->sanitize($report),
            ];

            if (! (bool) config('capell-exception-reports.webhook.include_trace', false)) {
                unset($payload['report']['trace']);
            }

            Http::acceptJson()
                ->timeout($this->positiveIntegerConfig('capell-exception-reports.webhook.timeout_seconds', 5))
                ->post($url, $payload)
                ->throw();
        } catch (Throwable $webhookFailure) {
            $this->logWebhookFailure($webhookFailure, $exception);
        }
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

    private function logWebhookFailure(Throwable $webhookFailure, Throwable $originalException): void
    {
        try {
            Log::warning(
                'Exception Reports failed to deliver an exception webhook.',
                resolve(ExceptionReportMailSanitizer::class)->sanitizeLogContext([
                    'webhook_exception' => $webhookFailure::class,
                    'webhook_message' => Str::limit($webhookFailure->getMessage(), 500, '...'),
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
