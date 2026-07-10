<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\QueueRateLimitedExceptionDigestAction;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Actions\SendExceptionReportWebhookAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;

/**
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function exceptionReportsTestArrayValue(array $values, string $key): array
{
    $value = $values[$key] ?? [];

    if (! is_array($value)) {
        throw new RuntimeException(sprintf('Expected [%s] to be an array.', $key));
    }

    $result = [];

    foreach ($value as $childKey => $childValue) {
        if (! is_string($childKey)) {
            throw new RuntimeException(sprintf('Expected [%s] to have string keys.', $key));
        }

        $result[$childKey] = $childValue;
    }

    return $result;
}

/**
 * @param  array<string, mixed>  $values
 */
function exceptionReportsTestStringValue(array $values, string $key): ?string
{
    $value = $values[$key] ?? null;

    return is_string($value) ? $value : null;
}

beforeEach(function (): void {
    Cache::flush();
    config()->set('capell-exception-reports.recipient', 'alerts@example.com');
});

it('queues exception reports by email', function (): void {
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $summary = exceptionReportsTestArrayValue($mail->report, 'summary');
        $trace = exceptionReportsTestStringValue($mail->report, 'trace') ?? '';

        return $mail->hasTo('alerts@example.com')
            && $summary['exception'] === RuntimeException::class
            && $summary['message'] === 'Something broke'
            && $trace === 'n/a';
    });
});

it('does not serialize request secrets into queued exception reports', function (): void {
    Mail::fake();

    Route::get('/exception-report-queue/{token}', function (): never {
        throw new RuntimeException('Provider failure token=message-secret');
    });

    $this->get('/exception-report-queue/route-secret?signature=query-secret');

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $serializedMail = serialize($mail);

        return ! str_contains($serializedMail, 'route-secret')
            && ! str_contains($serializedMail, 'query-secret')
            && ! str_contains($serializedMail, 'message-secret');
    });
});

it('queues grouped digest emails for repeated rate limited exception signatures', function (): void {
    Mail::fake();
    config()->set('capell-exception-reports.digest.enabled', true);
    config()->set('capell-exception-reports.digest.threshold', 2);
    config()->set('capell-exception-reports.digest.window_seconds', 900);

    $exception = new RuntimeException('Digest this repeated failure');

    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);

    Mail::assertQueued(UnhandledExceptionReported::class, 2);
    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $digest = exceptionReportsTestArrayValue($mail->report, 'digest');
        $subject = $mail->envelope()->subject ?? '';

        return $mail->hasTo('alerts@example.com')
            && str_contains($subject, 'Digest: 2 repeated')
            && $digest['count'] === 2
            && $digest['threshold'] === 2
            && $digest['window_seconds'] === 900
            && is_string($digest['signature'])
            && str_contains((string) $mail->render(), 'Digest')
            && str_contains((string) $mail->render(), '2 suppressed reports');
    });
});

it('queues rate limited digest emails through a dedicated action', function (): void {
    Mail::fake();
    config()->set('capell-exception-reports.digest.enabled', true);
    config()->set('capell-exception-reports.digest.threshold', 2);
    config()->set('capell-exception-reports.digest.window_seconds', 900);

    $exception = new RuntimeException('Digest action failure');
    $report = [
        'subject' => '[Capell] RuntimeException in file: Example.php:10',
        'source' => 'file: Example.php:10',
        'summary' => [
            'exception' => RuntimeException::class,
            'message' => 'Digest action failure',
        ],
        'request' => [],
        'console' => null,
        'user' => null,
        'trace' => 'trace',
    ];

    QueueRateLimitedExceptionDigestAction::run($exception, 'alerts@example.com', $report);
    QueueRateLimitedExceptionDigestAction::run($exception, 'alerts@example.com', $report);

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $digest = exceptionReportsTestArrayValue($mail->report, 'digest');

        return $mail->hasTo('alerts@example.com')
            && str_contains($mail->envelope()->subject ?? '', 'Digest: 2 repeated')
            && $digest['count'] === 2
            && $digest['threshold'] === 2
            && $digest['window_seconds'] === 900;
    });
});

it('posts sanitized exception reports to an optional webhook destination', function (): void {
    Mail::fake();
    Http::fake([
        'https://hooks.example.com/exception-reports' => Http::response(['ok' => true]),
    ]);

    config()->set('capell-exception-reports.webhook.enabled', true);
    config()->set('capell-exception-reports.webhook.url', 'https://hooks.example.com/exception-reports');

    ReportExceptionByEmailAction::run(new RuntimeException('Webhook failed token=secret-token'));

    Mail::assertQueued(UnhandledExceptionReported::class, 1);
    Http::assertSent(function (HttpClientRequest $request): bool {
        $report = $request['report'];
        $summary = is_array($report) && is_array($report['summary'] ?? null)
            ? $report['summary']
            : null;

        return $request->url() === 'https://hooks.example.com/exception-reports'
            && $request['event'] === 'exception.reported'
            && $request['package'] === 'capell-app/exception-reports'
            && is_array($report)
            && is_array($summary)
            && ($summary['exception'] ?? null) === RuntimeException::class
            && ($summary['message'] ?? null) === 'Webhook failed token=[redacted]'
            && ! array_key_exists('trace', $report);
    });
});

it('delivers sanitized webhook payloads through a dedicated action', function (): void {
    Http::fake([
        'https://hooks.example.com/exception-reports' => Http::response(['ok' => true]),
    ]);

    SendExceptionReportWebhookAction::run(
        'https://hooks.example.com/exception-reports',
        [
            'subject' => '[Capell] RuntimeException in webhook test',
            'source' => 'route: webhook.test',
            'summary' => [
                'exception' => RuntimeException::class,
                'message' => 'Webhook action failed token=secret-token',
            ],
            'request' => [],
            'console' => null,
            'user' => null,
            'trace' => 'secret trace',
        ],
        new RuntimeException('Webhook action failure'),
    );

    Http::assertSent(function (HttpClientRequest $request): bool {
        $report = $request['report'];
        $summary = is_array($report) && is_array($report['summary'] ?? null)
            ? $report['summary']
            : null;

        return $request->url() === 'https://hooks.example.com/exception-reports'
            && $request['event'] === 'exception.reported'
            && is_array($report)
            && is_array($summary)
            && ($summary['message'] ?? null) === 'Webhook action failed token=[redacted]'
            && ! array_key_exists('trace', $report);
    });
});

it('can deliver webhook reports when no email recipient is configured', function (): void {
    Mail::fake();
    Http::fake([
        'https://hooks.example.com/exception-reports' => Http::response(['ok' => true]),
    ]);

    config()->set('capell-exception-reports.recipient', null);
    config()->set('services.exception_reports.to', null);
    config()->set('capell-exception-reports.webhook.enabled', true);
    config()->set('capell-exception-reports.webhook.url', 'https://hooks.example.com/exception-reports');

    ReportExceptionByEmailAction::run(new RuntimeException('Webhook only failure'));

    Mail::assertNothingQueued();
    Http::assertSentCount(1);
});

it('never masks cache binding failures', function (): void {
    Mail::fake();
    $log = Log::spy();

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->andThrow(new BindingResolutionException('Target class [cache.store] does not exist. token=cache-secret'));

    ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));

    Mail::assertNothingQueued();
    $log->shouldHaveReceived('warning')
        ->once()
        ->with(
            'Exception Reports failed to queue an exception email.',
            Mockery::on(fn (array $context): bool => ($context['reporter_exception'] ?? null) === BindingResolutionException::class
                && ($context['reporter_message'] ?? null) === 'Target class [cache.store] does not exist. token=[redacted]'
                && ($context['original_exception'] ?? null) === RuntimeException::class),
        );
});

it('uses the registered exception reporter without masking resolution failures', function (): void {
    $log = Log::spy();

    app()->bind(ReportExceptionByEmailAction::class, function (): never {
        throw new BindingResolutionException('Target class [cache.store] does not exist. password=provider-secret');
    });

    expect(function (): void {
        report(new RuntimeException('Original failure token=original-secret'));
    })
        ->not->toThrow(BindingResolutionException::class);

    $log->shouldHaveReceived('warning')
        ->once()
        ->with(
            'Exception Reports failed to run the exception reporter.',
            Mockery::on(fn (array $context): bool => ($context['reporter_exception'] ?? null) === BindingResolutionException::class
                && ($context['reporter_message'] ?? null) === 'Target class [cache.store] does not exist. password=[redacted]'
                && ($context['original_message'] ?? null) === 'Original failure token=[redacted]'),
        );
});

it('does not recurse when reporter failure logging fails', function (): void {
    Mail::fake();

    Log::shouldReceive('warning')
        ->once()
        ->andThrow(new RuntimeException('Logger failed token=logger-secret'));

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->andThrow(new BindingResolutionException('Target class [cache.store] does not exist. token=cache-secret'));

    expect(function (): void {
        ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));
    })
        ->not->toThrow(RuntimeException::class);

    Mail::assertNothingQueued();
});

it('includes request user and route context', function (): void {
    Mail::fake();

    Route::get('/exception-report-context/{package}', function (string $package): string {
        ReportExceptionByEmailAction::run(new RuntimeException(sprintf('Package %s failed', $package)));

        return 'reported';
    })->name('exception-report.context');

    $this
        ->withHeaders([
            'Accept' => 'text/html',
            'Referer' => 'https://capell.test/dashboard',
            'User-Agent' => 'Mozilla/5.0 Exception Reporter Test Browser',
            'X-Request-Id' => 'req_exception_report_test',
        ])
        ->get('/exception-report-context/capell-marketplace')
        ->assertOk();

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $request = exceptionReportsTestArrayValue($mail->report, 'request');

        return $mail->report['subject'] === '[Capell] RuntimeException in route: exception-report.context'
            && $mail->envelope()->subject === $mail->report['subject']
            && $mail->report['source'] === 'route: exception-report.context'
            && $request['route_name'] === 'exception-report.context'
            && $request['route_parameters'] === ['package' => 'capell-marketplace']
            && $request['referer'] === 'https://capell.test/dashboard'
            && $request['browser'] === 'Mozilla/5.0 Exception Reporter Test Browser'
            && $request['accept'] === 'text/html'
            && $request['request_id'] === 'req_exception_report_test'
            && $mail->report['user'] === null;
    });
});

it('includes console command context when reporting artisan option parsing failures', function (): void {
    Mail::fake();

    $_SERVER['argv'] = ['artisan', 'migrate', '--force', '--columns=120', '--api-token=secret-token'];

    try {
        Artisan::call('migrate', [
            '--force' => true,
            '--columns' => '120',
        ]);
    } catch (ConsoleException $exception) {
        ReportExceptionByEmailAction::run($exception);
    }

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $console = exceptionReportsTestArrayValue($mail->report, 'console');

        return $mail->report['source'] === 'command: migrate'
            && $mail->report['subject'] === '[Capell] Symfony\Component\Console\Exception\InvalidOptionException in command: migrate'
            && $console['command'] === 'migrate'
            && $console['arguments'] === 'migrate --force --columns=120 --api-token=***'
            && $console['command_line'] === 'migrate --force --columns=120 --api-token=***'
            && str_contains((string) $mail->render(), 'Console')
            && str_contains((string) $mail->render(), '--columns=120')
            && ! str_contains((string) $mail->render(), 'secret-token');
    });
});

it('wraps long stack traces for email clients', function (): void {
    $mail = new UnhandledExceptionReported([
        'subject' => '[Capell] RuntimeException in route: very.long.route.name',
        'source' => 'route: very.long.route.name',
        'summary' => [
            'app' => 'Capell',
            'environment' => 'testing',
            'exception' => RuntimeException::class,
            'message' => 'Something broke with a very long class name',
            'file' => '/very/long/path/to/app/Actions/Marketplace/ExtremelyLongExceptionSourceName.php',
            'line' => 123,
            'reported_at' => now()->toDayDateTimeString(),
        ],
        'request' => [
            'method' => 'GET',
            'url' => 'https://capell.test/very/long/path',
            'path' => 'very/long/path',
            'route_name' => 'very.long.route.name',
            'route_action' => 'App\\Http\\Controllers\\VeryLongControllerNameThatShouldWrap@show',
            'route_parameters' => ['package' => 'vendor/extremely-long-package-name-that-should-wrap'],
            'ip_address' => '127.0.0.1',
            'referer' => 'https://capell.test/dashboard',
            'browser' => 'Mozilla/5.0 Long Browser Name That Should Wrap In Email Clients',
            'accept' => 'text/html',
            'request_id' => 'req_long_trace_test',
        ],
        'user' => [
            'id' => 1,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ],
        'trace' => '#0 /very/long/path/App/Actions/Marketplace/VeryLongClassNameThatWouldNormallyOverflowEmailClients.php(123): App\\Long\\Namespaced\\ClassName->handle()',
    ]);

    $mail->assertSeeInHtml('Stack Trace');
    $mail->assertSeeInHtml('<table', false);
    $mail->assertSeeInHtml('white-space: pre-wrap', false);
    $mail->assertSeeInHtml('overflow-wrap: anywhere', false);
    $mail->assertSeeInHtml('VeryLongClassNameThatWouldNormallyOverflowEmailClients');
    $mail->assertSeeInHtml('Route Parameters');
    $mail->assertDontSeeInHtml('&lt;table class="panel"', false);
    $mail->assertDontSeeInHtml('&lt;div class="table"&gt;', false);
    $mail->assertDontSeeInHtml('## Request', false);
    $mail->assertDontSeeInHtml('| Key | Value |');
});

it('strips unsafe diagnostic content and marks the report unsafe', function (): void {
    $mail = new UnhandledExceptionReported([
        'subject' => "[Capell] <script>alert('subject')</script> bad",
        'source' => "route: broken\n<script>alert('source')</script>",
        'summary' => [
            'app' => '<img src=x onerror=alert(1)>Capell',
            'environment' => 'testing',
            'exception' => RuntimeException::class,
            'message' => '<svg><script>alert(1)</script></svg> Something | broke',
            'file' => '/tmp/<script>alert(1)</script>/Example.php',
            'line' => 123,
            'reported_at' => now()->toDayDateTimeString(),
        ],
        'request' => [
            'method' => "GET\n| Injected | row |",
            'url' => 'https://capell.test/<script>alert(1)</script>',
            'path' => 'unsafe',
            'route_name' => 'unsafe.route',
            'route_action' => '<iframe src="https://evil.example"></iframe>Action',
            'route_parameters' => ['package' => '<script>alert("param")</script>clean'],
            'ip_address' => '127.0.0.1',
            'referer' => 'javascript:alert(1)',
            'browser' => '<img src=x onerror=alert(1)>Mozilla',
            'accept' => 'text/html',
            'request_id' => '<script>alert("request")</script>req',
        ],
        'user' => [
            'id' => 1,
            'name' => '<script>alert("name")</script>Ada',
            'email' => 'ada@example.com',
        ],
        'trace' => "#0 <script>alert('trace')</script>\n#1 App\\Safe\\Class->handle()",
    ]);

    $mail->assertSeeInHtml('Unsafe diagnostic content was stripped');
    $mail->assertSeeInHtml('summary.message');
    $mail->assertSeeInHtml('request.route_parameters.package');
    $mail->assertSeeInHtml('trace');
    $mail->assertSeeInHtml('Something');
    $mail->assertSeeInHtml('broke');
    $mail->assertSeeInHtml('clean');
    $mail->assertSeeInHtml('App\\Safe\\Class-&gt;handle()', false);
    $mail->assertDontSeeInHtml('<script', false);
    $mail->assertDontSeeInHtml('<iframe', false);
    $mail->assertDontSeeInHtml('<svg', false);
    $mail->assertDontSeeInHtml('onerror', false);
    $mail->assertDontSeeInHtml('| Injected | row |', false);

    expect($mail->envelope()->subject)->toBe('[Capell] bad');
});

it('redacts secrets from diagnostic email context', function (): void {
    $mail = new UnhandledExceptionReported([
        'subject' => '[Capell] RuntimeException in https://capell.test/reset?token=subject-secret',
        'source' => 'route: secret.route',
        'summary' => [
            'app' => 'Capell',
            'environment' => 'testing',
            'exception' => RuntimeException::class,
            'message' => 'Payment failed password=summary-secret',
            'file' => '/tmp/Example.php',
            'line' => 123,
            'reported_at' => now()->toDayDateTimeString(),
        ],
        'request' => [
            'method' => 'GET',
            'url' => 'https://capell.test/orders?token=url-secret&signature=url-signature&safe=visible',
            'path' => 'orders',
            'route_name' => 'orders.show',
            'route_action' => 'OrdersController@show',
            'route_parameters' => [
                'package' => 'visible-package',
                'token' => 'route-token-secret',
                'api_key' => 'route-api-key-secret',
            ],
            'ip_address' => '127.0.0.1',
            'referer' => 'https://capell.test/login?password=referer-secret',
            'browser' => 'Mozilla/5.0',
            'accept' => 'text/html',
            'request_id' => 'req-secret',
        ],
        'console' => [
            'command' => 'sync',
            'arguments' => '--api-token=console-secret',
            'command_line' => 'sync --api-token=console-secret',
        ],
        'user' => [
            'id' => 1,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'remember_token' => 'remember-secret',
        ],
        'trace' => "Authorization: Bearer trace-secret\n#0 Client->request('https://api.example.test?api_key=trace-api-secret')",
    ]);

    $html = (string) $mail->render();

    $mail->assertSeeInHtml('Unsafe diagnostic content was stripped');
    $mail->assertSeeInHtml('request.url');
    $mail->assertSeeInHtml('request.route_parameters.token');
    $mail->assertSeeInHtml('request.route_parameters.api_key');
    $mail->assertSeeInHtml('console.arguments');
    $mail->assertSeeInHtml('trace');
    $mail->assertSeeInHtml('safe=visible');
    $mail->assertSeeInHtml('visible-package');
    $mail->assertSeeInHtml('[redacted]');

    expect($html)
        ->not->toContain('subject-secret')
        ->not->toContain('summary-secret')
        ->not->toContain('url-secret')
        ->not->toContain('url-signature')
        ->not->toContain('route-token-secret')
        ->not->toContain('route-api-key-secret')
        ->not->toContain('referer-secret')
        ->not->toContain('console-secret')
        ->not->toContain('remember-secret')
        ->not->toContain('trace-secret')
        ->not->toContain('trace-api-secret');
});

it('rate limits duplicate exception reports by signature', function (): void {
    Mail::fake();

    $exception = new RuntimeException('Same failure');

    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);

    Mail::assertQueued(UnhandledExceptionReported::class, 1);
});

it('globally caps exception report emails', function (): void {
    Mail::fake();

    foreach (range(1, 11) as $attempt) {
        ReportExceptionByEmailAction::run(new RuntimeException('Failure ' . $attempt));
    }

    Mail::assertQueued(UnhandledExceptionReported::class, 10);
});
