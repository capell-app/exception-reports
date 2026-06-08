<?php

declare(strict_types=1);

use Capell\ExceptionReports\Health\ExceptionReportsHealthCheck;

it('passes package health checks when configured', function (): void {
    $results = ExceptionReportsHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(4)
        ->and($results->every(fn ($result): bool => $result->passed))->toBeTrue()
        ->and(ExceptionReportsHealthCheck::passed())->toBeTrue();
});

it('fails the recipient health check when no recipient is configured', function (): void {
    config()->set('capell-exception-reports.recipient', null);

    $result = (new ExceptionReportsHealthCheck)->recipientConfiguredCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->toContain('EXCEPTION_REPORT_RECIPIENT');
});
