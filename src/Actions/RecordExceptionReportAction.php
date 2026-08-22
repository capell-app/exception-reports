<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Actions;

use Capell\ExceptionReports\Models\ExceptionReport;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Http\Request;
use Illuminate\Queue\Worker;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Persists a durable, queryable row for every exception the reporter sees,
 * independent of whether an email or webhook is configured or rate limited.
 * Mail and webhook delivery remain the notification channel; this action is
 * the incident record that survives a suppressed or undeliverable email.
 */
final class RecordExceptionReportAction
{
    use AsFake;
    use AsObject;

    public function handle(Throwable $exception, ?Request $request = null): void
    {
        if (! (bool) config('capell-exception-reports.persistence.enabled', true)) {
            return;
        }

        $job = $this->currentQueueJob();

        ExceptionReport::query()->create([
            'exception_class' => $exception::class,
            'message' => $this->sanitizedMessage($exception),
            'job_class' => $job?->resolveQueuedJobClass(),
            'job_id' => $job?->getJobId(),
            'url' => $this->sanitizedUrl($request),
            'reported_at' => now(),
        ]);
    }

    private function sanitizedMessage(Throwable $exception): string
    {
        $message = Str::limit($exception->getMessage(), 2000, '...');
        $sanitized = $this->sanitizer()->sanitizeLogContext(['message' => $message])['message'] ?? $message;

        return is_string($sanitized) ? $sanitized : $message;
    }

    private function sanitizedUrl(?Request $request): ?string
    {
        // The container always has a Request bound, even outside an HTTP
        // context (a bare console/queue boot resolves an empty request for
        // "/"). Mirror ReportExceptionByEmailAction::source()'s check for a
        // real dispatched request rather than that phantom default.
        if (! $request instanceof Request || $request->path() === '/') {
            return null;
        }

        $url = $request->url();

        if ($url === '') {
            return null;
        }

        $sanitized = $this->sanitizer()->sanitizeLogContext(['url' => $url])['url'] ?? $url;

        return is_string($sanitized) ? $sanitized : null;
    }

    /**
     * Resolve the queue job currently being processed by this worker, if any.
     *
     * Laravel's queue worker keeps the job it is processing on its own
     * `currentJob` property (cleared in a `finally` block once the job
     * finishes), and exceptions are reported via `$this->exceptions->report($e)`
     * before that clean-up runs, so it is available here for jobs that fail.
     */
    private function currentQueueJob(): ?QueueJob
    {
        if (! app()->bound('queue.worker')) {
            return null;
        }

        $worker = app('queue.worker');

        if (! $worker instanceof Worker) {
            return null;
        }

        return $worker->currentJob instanceof QueueJob ? $worker->currentJob : null;
    }

    private function sanitizer(): ExceptionReportMailSanitizer
    {
        return resolve(ExceptionReportMailSanitizer::class);
    }
}
