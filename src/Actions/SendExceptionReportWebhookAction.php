<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\Core\Support\Http\OutboundHttp;
use Capell\Core\Support\Http\OutboundHttpPolicy;
use Capell\ExceptionReports\Data\ExceptionReportData;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Capell\ExceptionReports\Support\ExceptionReportMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class SendExceptionReportWebhookAction
{
    use AsFake;
    use AsObject;

    public function handle(string $url, ExceptionReportData $report, Throwable $exception): void
    {
        try {
            $endpoint = $this->endpoint($url);
            $report = ExceptionReportData::from(resolve(ExceptionReportMailSanitizer::class)->sanitize($report->toArray()));
            $eventId = (string) Str::uuid();
            $timestamp = (string) now()->getTimestamp();
            $payload = [
                'event' => 'exception.reported',
                'event_id' => $eventId,
                'timestamp' => $timestamp,
                'package' => 'capell-app/exception-reports',
                'subject' => $report->subject,
                'report' => $report->toArray(),
            ];

            if (! (bool) config('capell-exception-reports.webhook.include_trace', false)) {
                unset($payload['report']['trace']);
            }

            // The guarded client resolves the host, refuses non-public addresses
            // and pins the connection to the address it checked.
            OutboundHttp::guarded($endpoint)
                ->acceptJson()
                ->withHeaders([
                    'X-Capell-Event-Id' => $eventId,
                    'X-Capell-Timestamp' => $timestamp,
                    'X-Capell-Signature' => $this->signature($timestamp, $payload),
                ])
                ->post(trim($url), $payload)
                ->throw();
        } catch (Throwable $webhookFailure) {
            $this->logWebhookFailure($webhookFailure, $exception);
        }
    }

    private function endpoint(string $url): OutboundHttpPolicy
    {
        $parts = parse_url(trim($url));

        throw_if(! is_array($parts), InvalidArgumentException::class, 'Exception report webhook URL must be an absolute HTTPS URL.');

        $scheme = is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : null;
        $rawHost = strtolower(is_string($parts['host'] ?? null) ? $parts['host'] : '');
        $host = trim($rawHost, " \t\n\r\0\x0B.[]");

        // The request goes to the host as written, so it must match the allowlist without normalising.
        throw_if($host !== $rawHost && '[' . $host . ']' !== $rawHost, InvalidArgumentException::class, 'Exception report webhook URL host is not approved.');

        throw_if($scheme !== 'https' || $host === '', InvalidArgumentException::class, 'Exception report webhook URL must be an absolute HTTPS URL.');
        throw_if(! in_array($host, $this->allowedHosts(), true), InvalidArgumentException::class, 'Exception report webhook URL host is not approved.');

        $port = $parts['port'] ?? 443;
        throw_if(! is_int($port) || $port < 1 || $port > 65535, InvalidArgumentException::class, 'Exception report webhook URL port is invalid.');

        return new OutboundHttpPolicy(
            schemes: ['https'],
            ports: [$port],
            connectTimeoutSeconds: $this->positiveIntegerConfig('capell-exception-reports.webhook.connect_timeout_seconds', 2),
            timeoutSeconds: $this->positiveIntegerConfig('capell-exception-reports.webhook.timeout_seconds', 5),
        );
    }

    /** @return list<string> */
    private function allowedHosts(): array
    {
        $hosts = config('capell-exception-reports.webhook.allowed_hosts', []);

        return is_array($hosts)
            ? array_values(array_filter(array_map(
                static fn (mixed $host): string => is_string($host) ? strtolower(trim($host)) : '',
                $hosts,
            ), static fn (string $host): bool => $host !== ''))
            : [];
    }

    /** @param array<string, mixed> $payload */
    private function signature(string $timestamp, array $payload): string
    {
        $secret = config('capell-exception-reports.webhook.signing_secret');
        throw_unless(is_string($secret) && $secret !== '', InvalidArgumentException::class, 'Exception report webhook signing secret is required.');

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $json, $secret);
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
                    'webhook_message' => ExceptionReportMessage::of($webhookFailure, 500),
                    'original_exception' => $originalException::class,
                    'original_message' => ExceptionReportMessage::of($originalException, 500),
                    'original_file' => $originalException->getFile(),
                    'original_line' => $originalException->getLine(),
                ]),
            );
        } catch (Throwable) {
            //
        }
    }
}
