<?php

declare(strict_types=1);

beforeEach(function (): void {
    require dirname(__DIR__, 2) . '/workbench/routes/screenshot-fixtures.php';
});

afterEach(function (): void {
    putenv('CAPELL_SCREENSHOT_FIXTURE');
    putenv('CAPELL_SCREENSHOT_APP_PATH');
});

it('hides the email fixture unless the screenshot workbench enables it', function (): void {
    $this->get('/screenshot-fixtures/exception-reports/email')->assertNotFound();
});

it('hides the email fixture in production even with the fixture variables set', function (): void {
    putenv('CAPELL_SCREENSHOT_FIXTURE=1');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    $originalEnvironment = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $this->get('/screenshot-fixtures/exception-reports/email')->assertNotFound();
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnvironment);
    }
});

it('hides the email fixture when the screenshot app path is another checkout', function (): void {
    putenv('CAPELL_SCREENSHOT_FIXTURE=1');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . sys_get_temp_dir());

    $this->get('/screenshot-fixtures/exception-reports/email')->assertNotFound();
});

it('renders the package mailable for the screenshot workbench', function (): void {
    putenv('CAPELL_SCREENSHOT_FIXTURE=1');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    $this->get('/screenshot-fixtures/exception-reports/email')
        ->assertOk()
        ->assertSee('RuntimeException')
        ->assertSee('checkout.store')
        ->assertSee('ConfirmOrderPaymentAction.php')
        ->assertDontSee('<script', false);
});
