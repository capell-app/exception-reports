<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Tests;

use Capell\Core\Facades\CapellCore;
use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Capell\Tests\AbstractTestCase;
use Livewire\LivewireServiceProvider;
use Override;

class ExceptionReportsTestCase extends AbstractTestCase
{
    protected function getPackageServiceName(): string
    {
        return 'capell-exception-reports';
    }

    /**
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            ExceptionReportsServiceProvider::class,
        ];
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(ExceptionReportsServiceProvider::$packageName);

        config()->set('app.name', 'Capell');
        config()->set('capell-exception-reports.recipient', 'alerts@example.com');
        config()->set('cache.default', 'array');
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'no-reply@example.com');
        config()->set('queue.default', 'sync');
    }
}
