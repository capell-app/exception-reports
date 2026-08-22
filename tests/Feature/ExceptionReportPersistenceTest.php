<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\RecordExceptionReportAction;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Models\ExceptionReport;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

beforeEach(function (): void {
    DB::table('exception_reports')->truncate();
    config()->set('capell-exception-reports.recipient', 'alerts@example.com');
    config()->set('capell-exception-reports.rate_limits.enabled', false);
    config()->set('capell-exception-reports.persistence.enabled', true);
    config()->set('services.exception_reports.to', null);
});

it('writes both a queued mail notification and a persisted exception report row', function (): void {
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Dual write failure'));

    Mail::assertQueued(UnhandledExceptionReported::class, 1);

    expect(ExceptionReport::query()->count())->toBe(1);

    $row = ExceptionReport::query()->sole();

    expect($row->exception_class)->toBe(RuntimeException::class)
        ->and($row->message)->toBe('Dual write failure')
        ->and($row->job_class)->toBeNull()
        ->and($row->job_id)->toBeNull()
        ->and($row->url)->toBeNull()
        ->and($row->reported_at)->not->toBeNull();
});

it('persists a nullable job and url context when reported outside a request or queue job', function (): void {
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Standalone console failure'));

    $row = ExceptionReport::query()->sole();

    expect($row->job_class)->toBeNull()
        ->and($row->job_id)->toBeNull()
        ->and($row->url)->toBeNull();
});

it('captures the request URL when an exception is reported from an HTTP request', function (): void {
    Mail::fake();

    Route::get('/exception-report-persistence/{token}', function (): string {
        ReportExceptionByEmailAction::run(new RuntimeException('HTTP request failure'));

        return 'reported';
    });

    $this->get('/exception-report-persistence/abc?signature=query-secret')->assertOk();

    $row = ExceptionReport::query()->sole();

    expect($row->url)->toBe(url('/exception-report-persistence/abc'))
        ->and($row->url)->not->toContain('query-secret')
        ->and($row->job_class)->toBeNull();
});

it('captures the resolved job class and id when reported while a queue worker is processing a job', function (): void {
    Mail::fake();

    $job = Mockery::mock(QueueJob::class);
    $job->shouldReceive('resolveQueuedJobClass')->andReturn('App\\Jobs\\ProcessPodcast');
    $job->shouldReceive('getJobId')->andReturn('job-id-123');

    /** @var Worker $worker */
    $worker = app('queue.worker');
    $worker->currentJob = $job;

    try {
        ReportExceptionByEmailAction::run(new RuntimeException('Failure during queued job'));
    } finally {
        $worker->currentJob = null;
    }

    $row = ExceptionReport::query()->sole();

    expect($row->job_class)->toBe('App\\Jobs\\ProcessPodcast')
        ->and($row->job_id)->toBe('job-id-123')
        ->and($row->url)->toBeNull();
});

it('redacts sensitive tokens from the exception message before persisting it', function (): void {
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Auth failed token=super-secret-value'));

    $row = ExceptionReport::query()->sole();

    expect($row->message)->not->toContain('super-secret-value')
        ->and($row->message)->toContain('[redacted]');
});

it('still records a DB row when no recipient or webhook is configured', function (): void {
    config()->set('capell-exception-reports.recipient', null);
    config()->set('services.exception_reports.to', null);
    config()->set('capell-exception-reports.webhook.enabled', false);
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('No delivery channel configured'));

    Mail::assertNothingQueued();
    expect(ExceptionReport::query()->count())->toBe(1);
});

it('still records a DB row for exceptions suppressed by email rate limiting', function (): void {
    config()->set('capell-exception-reports.rate_limits.enabled', true);
    config()->set('capell-exception-reports.rate_limits.signature_attempts', 1);
    Mail::fake();

    $exception = new RuntimeException('Same rate limited failure');

    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);

    Mail::assertQueued(UnhandledExceptionReported::class, 1);
    expect(ExceptionReport::query()->count())->toBe(2);
});

it('does not persist an exception report when persistence is disabled via config', function (): void {
    config()->set('capell-exception-reports.persistence.enabled', false);
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Persistence disabled'));

    Mail::assertQueued(UnhandledExceptionReported::class, 1);
    expect(ExceptionReport::query()->count())->toBe(0);
});

it('still queues mail even if persisting the exception report fails', function (): void {
    Mail::fake();
    $log = Log::spy();

    app()->bind(
        RecordExceptionReportAction::class,
        function (): never {
            throw new RuntimeException('DB unavailable for exception reports');
        },
    );

    ReportExceptionByEmailAction::run(new RuntimeException('Original failure'));

    Mail::assertQueued(UnhandledExceptionReported::class, 1);
    expect(ExceptionReport::query()->count())->toBe(0);
    $log->shouldHaveReceived('warning')
        ->once()
        ->with(
            'Exception Reports failed to persist an exception report.',
            Mockery::on(fn (array $context): bool => ($context['reporter_exception'] ?? null) === RuntimeException::class
                && ($context['reporter_message'] ?? null) === 'DB unavailable for exception reports'
                && ($context['original_message'] ?? null) === 'Original failure'),
        );
});

it('does not persist inactive Postmark bounce noise', function (): void {
    Mail::fake();

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getContent')
        ->with(false)
        ->andReturn(json_encode(['ErrorCode' => 406, 'Message' => 'Inactive recipient'], JSON_THROW_ON_ERROR));

    ReportExceptionByEmailAction::run(
        new HttpTransportException('Unable to send an email', $response),
    );

    Mail::assertNothingQueued();
    expect(ExceptionReport::query()->count())->toBe(0);
});
