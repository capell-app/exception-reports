<?php

declare(strict_types=1);

use Capell\Core\Actions\DisablePackageAction;
use Capell\Core\Actions\EnablePackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Data\Manifest\ExtensionProviderData;
use Capell\Core\Facades\CapellCore;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Capell\ExceptionReports\Tests\Integration\Providers\ExceptionReportsRecordingAction;
use Capell\ExceptionReports\Tests\Integration\Providers\ExceptionReportsStoppingProvider;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

it('suppresses inactive Postmark recipients before installation even when email reporting is disabled', function (bool $enabled): void {
    InstalledRuntimeState::run(static function () use ($enabled): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('exception-reports'), false);
        expect(CapellCore::isPackageInstalled(ExceptionReportsServiceProvider::$packageName))->toBeFalse();
        config()->set('capell-exception-reports.enabled', $enabled);
        config()->set('capell-exception-reports.suppressed_mail_failures.logging.enabled', false);

        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        $log = Log::spy();
        $handler = $app->get(ExceptionHandler::class);
        throw_unless($handler instanceof Handler, RuntimeException::class);
        $followingReports = 0;
        $handler->reportable(static function (Throwable $exception) use (&$followingReports): void {
            $followingReports++;
        });
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getContent')->with(false)->andReturn('{"ErrorCode":406}');

        $handler->report(new HttpTransportException('Inactive recipient', $response));

        expect($action->exceptions)->toBe([])
            ->and($followingReports)->toBe(0);
        $log->shouldNotHaveReceived('error');
    });
})->with([true, false]);

it('reports ordinary exceptions before installation and respects configuration disabling', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('exception-reports'), false);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $handler = $app->get(ExceptionHandler::class);
        $exception = new RuntimeException('Before installation');

        $handler->report($exception);
        config()->set('capell-exception-reports.enabled', false);
        $handler->report(new RuntimeException('Reporting disabled'));

        expect($action->exceptions)->toBe([$exception]);
    });
});

it('reports before another provider that registers a stopping reporter before boot completes', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $app = InstalledRuntimeTestCase::boot(
            InstalledRuntimeParity::manifest('exception-reports'),
            true,
            static function (Application $app): void {
                $app->singleton(ExceptionHandler::class, static fn (): Handler => new Handler($app));
                $app->register(ExceptionReportsStoppingProvider::class);
            },
        );
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $exception = new RuntimeException('Reporter order');

        $app->make(ExceptionHandler::class)->report($exception);

        expect($action->exceptions)->toBe([$exception]);
    });
});

it('attaches one reporter to an already-resolved transient handler and each replacement', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, false, includeTarget: false);
        CapellCore::markPackageInstalled($manifest['name']);
        $handlers = [];
        $app->bind(ExceptionHandler::class, static function () use ($app, &$handlers): Handler {
            $handler = new Handler($app);
            $handlers[] = $handler;

            return $handler;
        });
        $app->make(ExceptionHandler::class);
        $app->register(ExceptionReportsServiceProvider::class);

        expect($handlers)->toHaveCount(2);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $exception = new RuntimeException('Already resolved transient handler');

        $handlers[1]->report($exception);

        expect($action->exceptions)->toBe([$exception]);
        $replacementException = new RuntimeException('Replacement handler');
        $app->make(ExceptionHandler::class)->report($replacementException);

        expect($action->exceptions)->toBe([$exception, $replacementException]);
    });
});

it('attaches one reporter when enabled late and retains it through repeated refresh', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, false, includeTarget: false);
        expect($app->getProvider(ExceptionReportsServiceProvider::class))->toBeNull();
        $handler = $app->make(ExceptionHandler::class);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $handler->report(new RuntimeException('Provider not loaded'));
        expect($action->exceptions)->toBe([]);
        $package = CapellCore::getPackage($manifest['name']);
        throw_unless($package->manifest !== null, RuntimeException::class);
        $package->manifest->providers = ExtensionProviderData::from($manifest['providers']);

        EnablePackageAction::run($package);
        $exception = new RuntimeException('Enabled late');
        $handler->report($exception);
        expect($app->getProvider(ExceptionReportsServiceProvider::class))->toBeInstanceOf(ExceptionReportsServiceProvider::class)
            ->and($app->make(ExceptionHandler::class))->toBe($handler)
            ->and($action->exceptions)->toBe([$exception]);

        RefreshInstalledPackageRuntimeAction::run($package);
        RefreshInstalledPackageRuntimeAction::run($package);
        $refreshedException = new RuntimeException('Refreshed twice');
        $handler->report($refreshedException);

        expect($app->make(ExceptionHandler::class))->toBe($handler)
            ->and($action->exceptions)->toBe([$exception, $refreshedException]);
    });
});

it('stops reporting exceptions once the package is disabled in the running process', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, true);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $handler = $app->make(ExceptionHandler::class);
        $enabledException = new RuntimeException('Package enabled');
        $handler->report($enabledException);

        DisablePackageAction::run(CapellCore::getPackage($manifest['name']));
        $handler->report(new RuntimeException('Package disabled'));
        $app->make(ExceptionHandler::class)->report(new RuntimeException('Package disabled, resolved again'));

        expect(CapellCore::isPackageInstalled($manifest['name']))->toBeFalse()
            ->and($action->exceptions)->toBe([$enabledException]);
    });
});

it('restores exactly one reporter when a disabled package is enabled again', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, true);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $handler = $app->make(ExceptionHandler::class);
        $package = CapellCore::getPackage($manifest['name']);

        DisablePackageAction::run($package);
        $handler->report(new RuntimeException('Package disabled'));
        EnablePackageAction::run($package);
        RefreshInstalledPackageRuntimeAction::run($package);
        $reenabledException = new RuntimeException('Package enabled again');
        $handler->report($reenabledException);

        expect(CapellCore::isPackageInstalled($manifest['name']))->toBeTrue()
            ->and($app->make(ExceptionHandler::class))->toBe($handler)
            ->and($action->exceptions)->toBe([$reenabledException]);
    });
});

it('keeps pre-installation reporting and inactive recipient suppression when the installed runtime never activated', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, false);
        expect(CapellCore::isPackageInstalled($manifest['name']))->toBeFalse();
        config()->set('capell-exception-reports.suppressed_mail_failures.logging.enabled', false);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();
        $handler = $app->get(ExceptionHandler::class);
        throw_unless($handler instanceof Handler, RuntimeException::class);
        $followingReports = 0;
        $handler->reportable(static function (Throwable $exception) use (&$followingReports): void {
            $followingReports++;
        });
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getContent')->with(false)->andReturn('{"ErrorCode":406}');
        $ordinaryException = new RuntimeException('Before installation');

        $handler->report(new HttpTransportException('Inactive recipient', $response));
        $handler->report($ordinaryException);

        expect($action->exceptions)->toBe([$ordinaryException])
            ->and($followingReports)->toBe(1);
    });
});

it('does not report when the process starts with the package recorded as disabled', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('exception-reports');
        $app = InstalledRuntimeTestCase::boot($manifest, false);
        // Persisted state from an earlier process: installed, then disabled; this process never activates the runtime.
        CapellCore::markPackageInstalled($manifest['name']);
        CapellCore::markPackageDisabled($manifest['name']);
        $action = new ExceptionReportsRecordingAction;
        $app->instance(ReportExceptionByEmailAction::class, $action);
        Log::spy();

        $app->make(ExceptionHandler::class)->report(new RuntimeException('Started disabled'));

        expect(CapellCore::isPackageInstalled($manifest['name']))->toBeFalse()
            ->and($action->exceptions)->toBe([]);
    });
});
