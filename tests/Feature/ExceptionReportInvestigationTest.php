<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Models\ExceptionReport;
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
