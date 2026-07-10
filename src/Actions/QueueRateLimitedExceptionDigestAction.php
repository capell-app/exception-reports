<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class QueueRateLimitedExceptionDigestAction
{
    use AsObject;

    /**
     * @param  array<string, mixed>  $report
     */
    public function handle(Throwable $exception, string $recipient, array $report): void
    {
        if (! (bool) config('capell-exception-reports.digest.enabled', false)) {
            return;
        }

        $report = resolve(ExceptionReportMailSanitizer::class)->sanitize($report);
        $signature = $this->signature($exception);
        $windowSeconds = $this->positiveIntegerConfig('capell-exception-reports.digest.window_seconds', 60 * 60);
        $threshold = $this->positiveIntegerConfig('capell-exception-reports.digest.threshold', 5);
        $cacheKey = 'exception-report-email:digest:' . $signature;

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, 0, $windowSeconds);
        }

        $count = Cache::increment($cacheKey);
        $count = is_int($count) ? $count : (int) $count;

        if ($count % $threshold !== 0) {
            return;
        }

        $digestReport = $report;
        $digestReport['subject'] = Str::limit(
            '[' . $this->appName() . '] Digest: ' . $count . ' repeated ' . $exception::class . ' reports',
            180,
            '...',
        );
        $digestReport['digest'] = [
            'count' => $count,
            'threshold' => $threshold,
            'window_seconds' => $windowSeconds,
            'signature' => $signature,
            'grouped_at' => now()->toDayDateTimeString(),
        ];

        Mail::to($recipient)->queue(new UnhandledExceptionReported($digestReport));
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
}
