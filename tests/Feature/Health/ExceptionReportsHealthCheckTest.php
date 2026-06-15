<?php

declare(strict_types=1);

use Capell\ExceptionReports\Health\ExceptionReportsHealthCheck;

it('passes package health checks when configured', function (): void {
    $results = ExceptionReportsHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(7)
        ->and($results->every(fn (mixed $result): bool => $result->passed))->toBeTrue()
        ->and(ExceptionReportsHealthCheck::passed())->toBeTrue();
});

it('fails the recipient health check when no recipient is configured', function (): void {
    config()->set('capell-exception-reports.recipient');

    $result = (new ExceptionReportsHealthCheck)->recipientConfiguredCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->toContain('EXCEPTION_REPORT_RECIPIENT');
});

it('passes the recipient health check when using the legacy recipient fallback', function (): void {
    config()->set('capell-exception-reports.recipient');
    config()->set('services.exception_reports.to', 'legacy-alerts@example.com');

    $result = (new ExceptionReportsHealthCheck)->recipientConfiguredCheck();

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toContain('legacy services.exception_reports.to');
});

it('fails the mailer health check when the configured mailer is missing', function (): void {
    config()->set('mail.default', 'missing');
    config()->set('mail.mailers.missing');

    $result = (new ExceptionReportsHealthCheck)->mailerConfiguredCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->toContain('MAIL_MAILER');
});

it('fails the from address health check when the configured from address is invalid', function (): void {
    config()->set('mail.from.address', 'not-an-email-address');

    $result = (new ExceptionReportsHealthCheck)->fromAddressConfiguredCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->toContain('MAIL_FROM_ADDRESS');
});

it('fails the queue health check when the configured queue connection is missing', function (): void {
    config()->set('queue.default', 'missing');
    config()->set('queue.connections.missing');

    $result = (new ExceptionReportsHealthCheck)->queueConnectionConfiguredCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->toContain('QUEUE_CONNECTION');
});
