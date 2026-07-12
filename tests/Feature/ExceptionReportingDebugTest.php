<?php

declare(strict_types=1);

use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

it('prints reporter failure', function (): void {
    Mail::fake();
    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
        fwrite(STDERR, json_encode([$message, $context], JSON_THROW_ON_ERROR) . PHP_EOL);

        return true;
    });

    ReportExceptionByEmailAction::run(new RuntimeException('Something broke'));
});
