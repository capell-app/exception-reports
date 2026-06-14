<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

beforeEach(function (): void {
    Cache::flush();
    config()->set('capell-exception-reports.recipient', 'alerts@example.com');
});

it('queues exception reports by email', function (): void {
    Mail::fake();

    ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));

    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail): bool {
        $summary = exceptionReportsTestArrayValue($mail->report, 'summary');
        $trace = $mail->report['trace'] ?? '';

        return $mail->hasTo('alerts@example.com')
            && $summary['exception'] === RuntimeException::class
            && $summary['message'] === 'Something broke'
            && is_string($trace)
            && str_contains($trace, __FILE__);
    });
});

it('never masks cache binding failures', function (): void {
    Mail::fake();

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->andThrow(new BindingResolutionException('Target class [cache.store] does not exist.'));

    ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));

    Mail::assertNothingQueued();
});

it('uses the registered exception reporter without masking resolution failures', function (): void {
    app()->bind(ReportExceptionByEmailAction::class, function (): never {
        throw new BindingResolutionException('Target class [cache.store] does not exist.');
    });

    expect(fn () => report(new RuntimeException('Original failure')))
        ->not->toThrow(BindingResolutionException::class);
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
