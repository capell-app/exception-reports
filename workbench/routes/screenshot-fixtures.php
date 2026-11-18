<?php

declare(strict_types=1);

use Capell\ExceptionReports\Data\ExceptionReportData;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

// Loaded only by the screenshot workbench, never by the package provider.
// Renders the package's own mailable, through its sanitiser and Markdown view,
// for a representative checkout failure so the capture shows the real email.
Route::get('/screenshot-fixtures/exception-reports/email', static function (): Response {
    $configuredPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
    abort_unless(
        app()->environment(['local', 'testing'])
            && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
            && is_string($configuredPath)
            && realpath($configuredPath) !== false
            && realpath($configuredPath) === realpath(base_path()),
        404,
    );

    $mailable = new UnhandledExceptionReported(ExceptionReportData::from([
        'subject' => '[Northwind Studio] RuntimeException in route: checkout.store',
        'source' => 'route: checkout.store',
        'summary' => [
            'app' => 'Northwind Studio',
            'environment' => 'production',
            'exception' => RuntimeException::class,
            'message' => 'The payment provider did not confirm the order within 30 seconds.',
            'file' => 'ConfirmOrderPaymentAction.php',
            'line' => 84,
            'reported_at' => 'Fri, 13 Nov 2026 9:42 AM',
        ],
        'request' => [
            'method' => 'POST',
            'path' => 'checkout',
            'route_name' => 'checkout.store',
            'route_parameters' => [],
        ],
        'console' => null,
        'user' => null,
        'trace' => '',
    ]));

    return response($mailable->render());
})->middleware('web')->name('screenshot-fixtures.exception-reports.email');
