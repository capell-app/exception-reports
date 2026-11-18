<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\RecordExceptionReportAction;
use Capell\ExceptionReports\Models\ExceptionReport;
use Capell\ExceptionReports\Support\ExceptionSignature;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    DB::table('exception_reports')->truncate();
    config()->set('capell-exception-reports.persistence.enabled', true);
});

it('does not persist the SQL statement or bound values of a query exception', function (): void {
    $exception = new QueryException(
        'mysql',
        'insert into users (name, address) values (?, ?)',
        ['Jane Citizen', '1 Private Road'],
        new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'Jane Citizen' for key 'users_name_unique'"),
    );

    RecordExceptionReportAction::run($exception);

    $message = ExceptionReport::query()->sole()->message;

    expect($message)->not->toContain('Jane Citizen')
        ->and($message)->not->toContain('1 Private Road')
        ->and($message)->toContain('SQLSTATE[23000]');
});

it('withholds multi-line and bracketed database messages without leaking row data', function (string $raw): void {
    RecordExceptionReportAction::run(new PDOException($raw));

    $message = ExceptionReport::query()->sole()->message;

    expect($message)->not->toContain('Jane Citizen')->and($message)->toContain('SQLSTATE[');
})->with([
    'postgres failing row' => ["SQLSTATE[23502]: Not null violation: 7 ERROR:  null value\nDETAIL:  Failing row contains (5, null, Jane Citizen)."],
    'mysql incorrect value' => ["SQLSTATE[HY000]: General error: 1366 Incorrect integer value: 'Jane Citizen' for column 'age'"],
    'value with newline' => ["SQLSTATE[23000]: Duplicate entry 'Jane\nCitizen' for key 'x'"],
]);

it('stores non-database messages unchanged apart from the existing sanitiser', function (): void {
    RecordExceptionReportAction::run(new RuntimeException('Cache store unavailable'));

    expect(ExceptionReport::query()->sole()->message)->toBe('Cache store unavailable');
});

it('treats a negative or non-numeric retention as the safe default or disabled, never a purge-all', function (mixed $value): void {
    config()->set('capell-exception-reports.persistence.retention_days', $value);
    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'fresh', 'reported_at' => now()->subDay()]);

    (new ExceptionReport)->prunable()->delete();

    expect(ExceptionReport::query()->count())->toBe(1);
})->with([-5, 'abc', null]);

it('prunes through the scheduled command', function (): void {
    config()->set('capell-exception-reports.persistence.retention_days', 7);
    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'old', 'reported_at' => now()->subDays(8)]);

    $this->artisan('capell:exception-reports-prune')->assertSuccessful();

    expect(ExceptionReport::query()->count())->toBe(0);
});

it('prunes reports older than the retention window and keeps recent ones', function (): void {
    config()->set('capell-exception-reports.persistence.retention_days', 30);

    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'old', 'reported_at' => now()->subDays(31)]);
    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'new', 'reported_at' => now()->subDays(2)]);

    $this->artisan('model:prune', ['--model' => [ExceptionReport::class]])->assertSuccessful();

    expect(ExceptionReport::query()->pluck('message')->all())->toBe(['new']);
});

it('keeps every report when retention is disabled', function (): void {
    config()->set('capell-exception-reports.persistence.retention_days', 0);

    ExceptionReport::query()->create(['exception_class' => 'A', 'message' => 'old', 'reported_at' => now()->subYears(2)]);

    $this->artisan('model:prune', ['--model' => [ExceptionReport::class]])->assertSuccessful();

    expect(ExceptionReport::query()->count())->toBe(1);
});

it('gives the same signature to messages that differ only in identifiers', function (): void {
    $sameLine = static fn (string $m): Throwable => new RuntimeException($m);
    $first = $sameLine("User 123 not found for 'abc'");
    $second = $sameLine("User 987 not found for 'xyz'");
    $other = $sameLine('Disk is full');

    expect(ExceptionSignature::for($first))->toBe(ExceptionSignature::for($second))
        ->and(ExceptionSignature::for($first))->not->toBe(ExceptionSignature::for($other));
});

it('keeps distinct double-quoted identifiers and uuid/hex values in the signature rules', function (): void {
    $make = static fn (string $m): Throwable => new RuntimeException($m);

    expect(ExceptionSignature::for($make('relation "users" does not exist')))
        ->not->toBe(ExceptionSignature::for($make('relation "orders" does not exist')))
        ->and(ExceptionSignature::for($make('Missing 3f2b8c1e-1a2b-4c3d-8e9f-0123456789ab')))
        ->toBe(ExceptionSignature::for($make('Missing 9a8b7c6d-1a2b-4c3d-8e9f-ffffffffffff')))
        ->and(ExceptionSignature::for($make('Token deadbeefdeadbeefdeadbeef bad')))
        ->toBe(ExceptionSignature::for($make('Token cafebabecafebabecafebabe bad')));
});
