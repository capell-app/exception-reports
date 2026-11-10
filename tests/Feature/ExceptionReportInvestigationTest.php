<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\RecordExceptionReportAction;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Models\ExceptionReport;
use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    DB::table('exception_reports')->truncate();
    config()->set('services.exception_reports.to');
    config()->set('capell-exception-reports.persistence.enabled', true);
});

it('locates sanitised incident evidence independently of absent or suppressed mail', function (bool $recipient): void {
    Mail::fake();
    config()->set('capell-exception-reports.recipient', $recipient ? 'alerts@example.com' : null);
    config()->set('capell-exception-reports.webhook.enabled', false);
    config()->set('capell-exception-reports.rate_limits.enabled', true);
    config()->set('capell-exception-reports.rate_limits.signature_attempts', 1);

    $startedAt = now()->subSecond();
    $exception = new RuntimeException('Investigation token=incident-secret');

    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);

    $rows = ExceptionReport::query()->where('reported_at', '>=', $startedAt)
        ->where('exception_class', RuntimeException::class)->orderByDesc('id')
        ->limit(20)->get(['id', 'reported_at', 'exception_class', 'message', 'job_class', 'job_id', 'url']);
    expect($rows)->toHaveCount(2);
    foreach ($rows as $row) {
        expect($row->message)->toContain('[redacted]')->not->toContain('incident-secret');
    }

    Mail::assertQueued(UnhandledExceptionReported::class, $recipient ? 1 : 0);
})->with(['mail absent' => false, 'mail suppressed' => true]);

it('does not persist credentials in unmatched request URLs', function (): void {
    $request = Request::create('https://example.test/calendar/private-feed-token.ics?key=other-secret');

    RecordExceptionReportAction::run(new RuntimeException('failure'), $request);

    $report = ExceptionReport::query()->latest('id')->firstOrFail();
    expect($report->url)->not->toContain('private-feed-token', 'other-secret');
});

it('redacts URL paths and query values from notification context', function (): void {
    $sanitizer = new ExceptionReportMailSanitizer;
    $result = $sanitizer->sanitizeLogContext([
        'message' => 'Request https://example.test/calendar/private-feed-token.ics?key=other-secret failed',
    ]);

    expect($result['message'])->toContain('[redacted]')->not->toContain('private-feed-token', 'other-secret');
});

it('redacts URL credentials from reports sent to mail and webhooks', function (): void {
    $sanitizer = new ExceptionReportMailSanitizer;
    $result = $sanitizer->sanitize([
        'request' => ['url' => 'https://example.test/preferences/secret-bearer?signature=secret-signature'],
    ]);

    $encoded = json_encode($result, JSON_THROW_ON_ERROR);
    expect($encoded)->toContain('[redacted]')->not->toContain('secret-bearer', 'secret-signature');
});
