<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\ExceptionReports\Data\ExceptionReportData;
use Capell\ExceptionReports\Data\ResolvedExceptionReportWebhookEndpointData;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Support\Facades\Http;
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

            Http::acceptJson()
                ->timeout($this->positiveIntegerConfig('capell-exception-reports.webhook.timeout_seconds', 5))
                ->connectTimeout($this->positiveIntegerConfig('capell-exception-reports.webhook.connect_timeout_seconds', 2))
                ->withoutRedirecting()
                ->withHeaders([
                    'Host' => $endpoint->hostHeader(),
                    'X-Capell-Event-Id' => $eventId,
                    'X-Capell-Timestamp' => $timestamp,
                    'X-Capell-Signature' => $this->signature($timestamp, $payload),
                ])
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [$endpoint->curlResolveEntry()]]])
                ->post($endpoint->url, $payload)
                ->throw();
        } catch (Throwable $webhookFailure) {
            $this->logWebhookFailure($webhookFailure, $exception);
        }
    }

    private function endpoint(string $url): ResolvedExceptionReportWebhookEndpointData
    {
        $parts = parse_url(trim($url));

        throw_if(! is_array($parts), InvalidArgumentException::class, 'Exception report webhook URL must be an absolute HTTPS URL.');

        $scheme = is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : null;
        $host = is_string($parts['host'] ?? null) ? strtolower(trim($parts['host'], " \t\n\r\0\x0B.[]")) : null;

        throw_if($scheme !== 'https' || $host === null || $host === '', InvalidArgumentException::class, 'Exception report webhook URL must be an absolute HTTPS URL.');
        throw_if(! in_array($host, $this->allowedHosts(), true), InvalidArgumentException::class, 'Exception report webhook URL host is not approved.');

        $addresses = $this->resolvedHostAddresses($host);

        throw_if($addresses === [], InvalidArgumentException::class, 'Exception report webhook URL host could not be resolved.');
        throw_if($this->hasPrivateAddress($addresses), InvalidArgumentException::class, 'Exception report webhook URL host is not public.');

        $port = $parts['port'] ?? 443;
        throw_if(! is_int($port) || $port < 1 || $port > 65535, InvalidArgumentException::class, 'Exception report webhook URL port is invalid.');

        return new ResolvedExceptionReportWebhookEndpointData($url, $host, $port, $addresses[0]);
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

    /** @return list<string> */
    private function resolvedHostAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @param list<string> $addresses */
    private function hasPrivateAddress(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
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
