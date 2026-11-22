<?php

declare(strict_types=1);

use Capell\ExceptionReports\Data\ExceptionReportData;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;

/**
 * @param  array<string, mixed>  $overrides
 */
function exceptionReportsSparseReport(array $overrides = []): ExceptionReportData
{
    return ExceptionReportData::from(array_replace_recursive([
        'subject' => '[Capell] RuntimeException in route: checkout.store',
        'source' => 'route: checkout.store',
        'summary' => [
            'app' => 'Northwind Studio',
            'environment' => 'production',
            'exception' => RuntimeException::class,
            'message' => 'The payment provider did not confirm the order.',
            'file' => 'ConfirmOrderPaymentAction.php',
            'line' => 84,
            'reported_at' => 'Fri, 13 Nov 2026 9:42 AM',
        ],
        'request' => [
            'method' => 'POST',
            'route_name' => 'checkout.store',
            'route_parameters' => [],
        ],
        'console' => [],
        'user' => [],
        'trace' => '',
    ], $overrides));
}

// Bug caught: a report with no trace, user details or most request fields printed
// "n/a" rows and an empty Stack Trace heading jammed against the previous section.
it('omits request and user rows and the stack trace section when their values are missing', function (): void {
    $html = (string) new UnhandledExceptionReported(exceptionReportsSparseReport())->render();

    expect($html)
        ->toContain('POST')
        ->toContain('checkout.store')
        ->not->toContain('n/a')
        ->not->toContain('Stack Trace')
        ->not->toContain('>Name<')
        ->not->toContain('>Email<')
        ->not->toContain('IP address')
        ->not->toContain('Request ID');
});

// Bug caught: hiding missing rows must not hide values that are present.
it('still renders present request, user and stack trace values, escaped', function (): void {
    $html = (string) new UnhandledExceptionReported(exceptionReportsSparseReport([
        'request' => [
            'path' => 'checkout/store',
            'ip_address' => '203.0.113.9',
            'request_id' => 'req_123',
        ],
        'user' => ['id' => 7, 'name' => 'Ada <b>Lovelace</b>'],
        'trace' => "#0 app/Actions/ConfirmOrderPaymentAction.php(84): fail()\n#1 {main}",
    ]))->render();

    expect($html)
        ->toContain('Stack Trace')
        ->toContain('ConfirmOrderPaymentAction.php(84): fail()')
        ->toContain('203.0.113.9')
        ->toContain('req_123')
        ->toContain('checkout/store')
        ->toContain('Ada')
        ->not->toContain('<b>Lovelace</b>')
        ->not->toContain('n/a');
});
