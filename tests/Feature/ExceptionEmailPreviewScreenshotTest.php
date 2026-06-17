<?php

declare(strict_types=1);

use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Symfony\Component\Process\Process;

it('keeps the committed email preview screenshot tied to the rendered mailable', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $repositoryPath = dirname(__DIR__, 4);
    $screenshotPath = $packagePath . '/docs/screenshots/exception-email-preview.png';
    $temporaryHtmlPath = $packagePath . '/docs/screenshots/.exception-email-preview.html';

    $mail = new UnhandledExceptionReported(exceptionReportsEmailPreviewReport());
    $html = (string) $mail->render();

    expect($html)
        ->toContain('RuntimeException')
        ->toContain('Route Parameters')
        ->toContain('Unsafe diagnostic content was stripped')
        ->toContain('[redacted]')
        ->not->toContain('secret-token')
        ->not->toContain('<script');

    if (getenv('CAPELL_REFRESH_EXCEPTION_REPORTS_EMAIL_SCREENSHOT') === '1') {
        file_put_contents($temporaryHtmlPath, $html);

        try {
            $process = new Process([
                'node',
                $packagePath . '/scripts/capture-email-preview.mjs',
                $temporaryHtmlPath,
                $screenshotPath,
            ], $repositoryPath);
            $process->setTimeout(60);
            $process->mustRun();
        } finally {
            if (is_file($temporaryHtmlPath)) {
                unlink($temporaryHtmlPath);
            }
        }
    }

    expect($screenshotPath)->toBeFile();

    $dimensions = getimagesize($screenshotPath);

    expect($dimensions)->toBeArray()
        ->and($dimensions[0] ?? null)->toBe(760)
        ->and($dimensions[1] ?? null)->toBeGreaterThan(1100)
        ->toBeLessThan(7000);
});

/**
 * @return array<string, mixed>
 */
function exceptionReportsEmailPreviewReport(): array
{
    return [
        'subject' => '[Capell] RuntimeException in route: marketplace.package.preview',
        'source' => 'route: marketplace.package.preview',
        'summary' => [
            'app' => 'Capell',
            'environment' => 'production',
            'exception' => RuntimeException::class,
            'message' => 'Package preview failed while loading webhook token=secret-token',
            'file' => '/srv/app/Actions/Marketplace/RenderPackagePreviewAction.php',
            'line' => 118,
            'reported_at' => 'Tue, Jun 16, 2026 09:15 AM',
        ],
        'request' => [
            'method' => 'GET',
            'url' => 'https://capell.example/admin/marketplace/exception-reports?token=secret-token',
            'path' => 'admin/marketplace/exception-reports',
            'route_name' => 'marketplace.package.preview',
            'route_action' => 'App\\Http\\Controllers\\MarketplacePreviewController@show',
            'route_parameters' => [
                'package' => 'capell-app/exception-reports',
                'site' => 'operations',
                'token' => 'secret-token',
            ],
            'ip_address' => '127.0.0.1',
            'referer' => 'https://capell.example/admin/marketplace',
            'browser' => 'Mozilla/5.0 Capell Operator Browser',
            'accept' => 'text/html,application/xhtml+xml',
            'request_id' => 'req_exception_reports_preview',
        ],
        'console' => [],
        'user' => [
            'id' => 42,
            'name' => 'Operations Lead',
            'email' => 'ops@example.com',
            'remember_token' => 'secret-token',
        ],
        'digest' => [
            'count' => 3,
            'threshold' => 3,
            'window_seconds' => 900,
            'grouped_at' => '2026-06-16T09:15:00+00:00',
        ],
        'trace' => "#0 /srv/app/routes/web.php(44): App\\Actions\\Marketplace\\RenderPackagePreviewAction->handle()\n"
            . "#1 /srv/app/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\\Http\\Controllers\\MarketplacePreviewController->show()\n"
            . 'Authorization: Bearer secret-token',
    ];
}
