<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\RecordExceptionReportAction;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Mail\UnhandledExceptionReported;
use Capell\ExceptionReports\Models\ExceptionReport;
use Capell\ExceptionReports\Support\ExceptionReportMessage;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** A database error whose message carries the statement and the person's bound values. */
function exceptionReportsQueryException(): QueryException
{
    return new QueryException(
        'mysql',
        'insert into customers (name, address) values (?, ?)',
        ['Jane Citizen', '1 Private Road'],
        new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'Jane Citizen' for key 'customers_name_unique'"),
    );
}

beforeEach(function (): void {
    DB::table('exception_reports')->truncate();
    RateLimiter::clear('exception-report-email:global');
    config()->set('capell-exception-reports.persistence.enabled', true);
});

it('keeps a query exception\'s bound values out of the emailed report, as the stored row does', function (): void {
    Mail::fake();
    $exception = exceptionReportsQueryException();
    expect($exception->getMessage())->toContain('Jane Citizen');

    ReportExceptionByEmailAction::run($exception);

    $stored = ExceptionReport::query()->sole()->message;
    Mail::assertQueued(UnhandledExceptionReported::class, function (UnhandledExceptionReported $mail) use ($stored): bool {
        $encoded = (string) json_encode($mail->report->toArray());
        $message = data_get($mail->report->toArray(), 'summary.message');

        return ! str_contains($encoded, 'Jane Citizen')
            && ! str_contains($encoded, '1 Private Road')
            && ! str_contains($encoded, 'insert into customers')
            && $message === $stored
            && str_contains($stored, 'SQLSTATE[23000]');
    });
});

it('keeps a query exception\'s bound values out of the webhook payload', function (): void {
    Mail::fake();
    Http::fake(['https://example.com/exception-reports' => Http::response(['ok' => true])]);
    config()->set('capell-exception-reports.webhook.enabled', true);
    config()->set('capell-exception-reports.webhook.url', 'https://example.com/exception-reports');
    config()->set('capell-exception-reports.webhook.allowed_hosts', ['example.com']);
    config()->set('capell-exception-reports.webhook.signing_secret', 'webhook-test-secret');

    ReportExceptionByEmailAction::run(exceptionReportsQueryException());

    Http::assertSentCount(1);
    Http::assertSent(static fn (HttpClientRequest $request): bool => ! str_contains($request->body(), 'Jane Citizen')
        && ! str_contains($request->body(), '1 Private Road')
        && ! str_contains($request->body(), 'insert into customers')
        && str_contains($request->body(), 'SQLSTATE[23000]'));
});

it('keeps a query exception\'s bound values out of the repeat digest', function (): void {
    Mail::fake();
    config()->set('capell-exception-reports.digest.enabled', true);
    config()->set('capell-exception-reports.digest.threshold', 1);

    ReportExceptionByEmailAction::run(exceptionReportsQueryException());
    ReportExceptionByEmailAction::run(exceptionReportsQueryException());

    Mail::assertQueued(UnhandledExceptionReported::class, 2);
    Mail::assertQueued(UnhandledExceptionReported::class, static fn (UnhandledExceptionReported $mail): bool => data_get($mail->report->toArray(), 'digest') !== null);
    Mail::assertNotQueued(UnhandledExceptionReported::class, static fn (UnhandledExceptionReported $mail): bool => str_contains((string) json_encode($mail->report->toArray()), 'Jane Citizen'));
});

it('keeps a query exception\'s bound values out of the reporter failure log', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mailer unavailable'));
    $log = Log::spy();

    ReportExceptionByEmailAction::run(exceptionReportsQueryException());

    $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => ! str_contains((string) json_encode($context), 'Jane Citizen')
        && str_contains((string) json_encode($context), 'SQLSTATE[23000]'));
});

it('schedules the daily prune of stored reports', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'capell:exception-reports-prune'));

    $event = $events->sole();

    expect($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('stores the same scrubbed message whether recorded directly or through the reporter', function (): void {
    RecordExceptionReportAction::run(exceptionReportsQueryException());

    expect(ExceptionReport::query()->sole()->message)->not->toContain('Jane Citizen');
});

it('prunes a backlog in chunks and keeps every report when retention is off', function (): void {
    config()->set('capell-exception-reports.persistence.retention_days', 7);
    $old = now()->subDays(8);
    DB::table('exception_reports')->insert(array_map(static fn (int $i): array => [
        'exception_class' => 'A', 'message' => 'old ' . $i, 'reported_at' => $old,
    ], range(1, 1005)));
    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'recent', 'reported_at' => now()->subDay()]);
    $deletes = [];
    DB::listen(static function ($query) use (&$deletes): void {
        if (str_starts_with(strtolower($query->sql), 'delete')) {
            $deletes[] = $query->sql;
        }
    });

    $this->artisan('capell:exception-reports-prune')->expectsOutputToContain('Pruned 1005 exception reports.')->assertSuccessful();

    expect(ExceptionReport::query()->pluck('message')->all())->toBe(['recent'])
        ->and(count($deletes))->toBeGreaterThan(1);

    config()->set('capell-exception-reports.persistence.retention_days', 0);
    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'ancient', 'reported_at' => now()->subYears(3)]);

    $this->artisan('capell:exception-reports-prune')->assertSuccessful();

    expect(ExceptionReport::query()->count())->toBe(2);
});

/** Throws from a call whose argument is a secret, so the trace would show it if arguments were kept. */
function exceptionReportsThrowWith(string $secret): never
{
    throw new RuntimeException('Login failed');
}

it('reports a trace without call arguments when traces are on', function (): void {
    Mail::fake();
    config()->set('capell-exception-reports.privacy.include_trace', true);
    $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

    try {
        exceptionReportsThrowWith('hunter2pass');
    } catch (RuntimeException $exception) {
        ReportExceptionByEmailAction::run($exception);
    } finally {
        ini_set('zend.exception_ignore_args', is_string($ignoreArgs) ? $ignoreArgs : '1');
    }

    Mail::assertQueued(UnhandledExceptionReported::class, static function (UnhandledExceptionReported $mail): bool {
        $trace = data_get($mail->report->toArray(), 'trace');

        if (! is_string($trace)) {
            return false;
        }

        return str_contains($trace, 'exceptionReportsThrowWith()')
            && ! str_contains($trace, 'hunter2')
            && str_contains($trace, '{main}');
    });
});

it('redacts an address that the message length limit would cut in half', function (): void {
    $message = str_repeat('x', 490) . ' jane.citizen@example.com failed';

    expect(ExceptionReportMessage::of(new RuntimeException($message), 500))->not->toContain('jane.cit');
});

it('keeps every email address out of the stored row, email, webhook and digest through the same redactor', function (string $address, string $fragment): void {
    Mail::fake();
    Http::fake(['https://example.com/exception-reports' => Http::response(['ok' => true])]);
    config()->set('capell-exception-reports.webhook.enabled', true);
    config()->set('capell-exception-reports.webhook.url', 'https://example.com/exception-reports');
    config()->set('capell-exception-reports.webhook.allowed_hosts', ['example.com']);
    config()->set('capell-exception-reports.webhook.signing_secret', 'webhook-test-secret');
    config()->set('capell-exception-reports.digest.enabled', true);
    config()->set('capell-exception-reports.digest.threshold', 1);
    $exception = new RuntimeException('Could not deliver the receipt to ' . $address . ' after 3 attempts');

    ReportExceptionByEmailAction::run($exception);
    ReportExceptionByEmailAction::run($exception);

    $stored = ExceptionReport::query()->first()?->message;
    expect($stored)->toBe('Could not deliver the receipt to [redacted-email] after 3 attempts');

    $leaks = static fn (string $payload): bool => str_contains($payload, $fragment) || str_contains($payload, '@');
    Mail::assertQueued(UnhandledExceptionReported::class, 2);
    Mail::assertNotQueued(UnhandledExceptionReported::class, static fn (UnhandledExceptionReported $mail): bool => $leaks((string) json_encode($mail->report->toArray(), JSON_UNESCAPED_UNICODE)));
    Mail::assertQueued(UnhandledExceptionReported::class, static fn (UnhandledExceptionReported $mail): bool => data_get($mail->report->toArray(), 'summary.message') === $stored);
    Http::assertSent(static fn (HttpClientRequest $request): bool => data_get($request->data(), 'report.summary.message') === $stored
        && ! $leaks((string) json_encode($request->data(), JSON_UNESCAPED_UNICODE)));
})->with([
    'ascii' => ['jane.citizen@example.com', 'citizen'],
    'accented local part' => ['josé.citizen@example.com', 'jos'],
    'internationalised domain' => ['jane.citizen@exämple.de', 'exämple'],
    'non-latin address' => ['用户@例子.广告', '用户'],
    'single-label host' => ['jane.citizen@localhost', 'localhost'],
    'plus address' => ['jane+receipts@mail.example.org', 'receipts'],
    'quoted local part' => ['"jane doe"@example.com', 'jane doe'],
    'ip literal host' => ['jane.citizen@[192.168.0.1]', '192.168'],
    'dotted ip host' => ['jane.citizen@10.1.2.3', '10.1.2'],
]);

it('redacts email addresses however they are encoded or escaped', function (string $message, string $expected): void {
    expect(ExceptionReportMessage::of(new RuntimeException($message), 2000))->toBe($expected);
})->with([
    'guzzle query string' => [
        'Client error: `GET https://api.test/members?email=jane%40example.com` resulted in a `404 Not Found` response',
        'Client error: `GET https://api.test/[redacted]?email=[redacted]` resulted in a `404 Not Found` response',
    ],
    'double-encoded query value' => ['lookup failed for ?q=jane%2540example.com', 'lookup failed for ?q=[redacted-email]'],
    'percent-encoded in prose' => ['no member jane%40example.com', 'no member [redacted-email]'],
    'mail query key' => ['retry ?mail=someone&page=2', 'retry ?mail=[redacted]&page=2'],
    'e-mail query key' => ['retry ?e-mail=someone', 'retry ?e-mail=[redacted]'],
    'json escaped unicode' => ['{"email":"jos\\u00e9@ex\\u00e4mple.de"}', '{"email":"[redacted-email]"}'],
    'json unicode' => ['{"email":"josé@exämple.de"}', '{"email":"[redacted-email]"}'],
    'json escaped at sign' => ['send to jane\\u0040example.com', 'send to [redacted-email]'],
    'html entity' => ['send to jane&#64;example.com', 'send to [redacted-email]'],
    'hex html entity' => ['send to jane&#x40;example.com', 'send to [redacted-email]'],
    'named html entity' => ['send to jane&commat;example.com', 'send to [redacted-email]'],
    'backslash escaped' => ['send to jane\\@example.com', 'send to [redacted-email]'],
    'fullwidth at sign' => ['send to jane＠example.com', 'send to [redacted-email]'],
    'small at sign' => ['send to jane﹫example.com', 'send to [redacted-email]'],
    'quoted local part' => ['bounce for "jane doe"@example.com', 'bounce for [redacted-email]'],
    'ip literal' => ['bounce for jane@[192.168.0.1]', 'bounce for [redacted-email]'],
    'ipv6 literal' => ['bounce for jane@[IPv6:2001:db8::1]', 'bounce for [redacted-email]'],
    'dotted ip' => ['bounce for user@1.2.3.4', 'bounce for [redacted-email]'],
    'after a backslash' => ['C:\\jane@example.com', 'C:\\[redacted-email]'],
    'after a percent sign' => ['rate 100%jane@example.com', 'rate 100%[redacted-email]'],
    'version stays' => ['capell-app/core@1.0 needs php@8.4', 'capell-app/core@1.0 needs php@8.4'],
]);

it('redacts addresses idempotently', function (): void {
    $once = ExceptionReportMessage::of(new RuntimeException('to jane%40example.com and "a b"@x.org and jos\\u00e9@ex.de'), 2000);

    expect(ExceptionReportMessage::of(new RuntimeException($once), 2000))->toBe($once)
        ->and($once)->not->toContain('jane')
        ->and($once)->not->toContain('x.org')
        ->and($once)->not->toContain('ex.de');
});

it('redacts pathological input in linear time without giving up', function (string $input): void {
    $started = hrtime(true);
    $result = ExceptionReportMessage::of(new RuntimeException($input), 200);
    $seconds = (hrtime(true) - $started) / 1e9;

    // A hang ceiling, not a speed budget: catastrophic backtracking takes minutes or hits the
    // PCRE limit, which would withhold the whole value.
    expect($seconds)->toBeLessThan(10.0)
        ->and($result)->not->toBe('[redacted]')
        ->and(preg_last_error())->toBe(PREG_NO_ERROR);
})->with([
    'letters' => [str_repeat('a', 100_000)],
    'at signs' => [str_repeat('a@', 50_000)],
    'dots then at' => [str_repeat('a.', 50_000) . '@'],
    'hyphenated host' => ['a@' . str_repeat('a-', 50_000)],
    'quotes' => [str_repeat('"a', 50_000)],
    'percent separators' => [str_repeat('a%40', 25_000)],
    'json escapes' => [str_repeat('\\u00e9', 16_000)],
    'entities' => [str_repeat('a&#64;', 16_000)],
    'brackets' => ['a@[' . str_repeat('1.', 50_000)],
    'very long address' => [str_repeat('a', 100_000) . '@example.com'],
]);

it('keeps an email address out of the reporter failure log', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mailer refused josé.citizen@exämple.de'));
    $log = Log::spy();

    ReportExceptionByEmailAction::run(new RuntimeException('Could not deliver to 用户@例子.广告'));

    $log->shouldHaveReceived('warning')->withArgs(static function (string $message, array $context): bool {
        $encoded = (string) json_encode($context, JSON_UNESCAPED_UNICODE);

        return ! str_contains($encoded, '@') && substr_count($encoded, '[redacted-email]') === 2;
    });
});

it('keeps a version constraint readable while redacting addresses', function (): void {
    expect(ExceptionReportMessage::of(new RuntimeException('capell-app/core@1.0 needs php@8.4 for jane@example.com'), 500))
        ->toBe('capell-app/core@1.0 needs php@8.4 for [redacted-email]');
});
