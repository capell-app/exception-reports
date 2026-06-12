<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Enums\PackageCapability;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Capell\ExceptionReports\Health\ExceptionReportsHealthCheck;
use Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider;
use Illuminate\Support\Facades\File;

/**
 * @return array<string, mixed>
 */
function exceptionReportsJsonFileArray(string $path): array
{
    $decoded = json_decode(
        File::get($path),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    if (! is_array($decoded)) {
        throw new RuntimeException(sprintf('Expected [%s] to contain a JSON object.', $path));
    }

    foreach (array_keys($decoded) as $key) {
        if (! is_string($key)) {
            throw new RuntimeException(sprintf('Expected [%s] to contain a JSON object with string keys.', $path));
        }
    }

    $result = [];

    foreach ($decoded as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException(sprintf('Expected [%s] to contain a JSON object with string keys.', $path));
        }

        $result[$key] = $value;
    }

    return $result;
}

/**
 * @param  array<array-key, mixed>  $values
 * @return array<array-key, mixed>
 */
function exceptionReportsManifestArray(array $values, string $key): array
{
    $value = $values[$key] ?? [];

    if (! is_array($value)) {
        throw new RuntimeException(sprintf('Expected manifest key [%s] to be an array.', $key));
    }

    return $value;
}

/**
 * @param  array<array-key, mixed>  $values
 * @return list<mixed>
 */
function exceptionReportsManifestList(array $values, string $key): array
{
    $value = exceptionReportsManifestArray($values, $key);

    if (! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected manifest key [%s] to be a list.', $key));
    }

    return $value;
}

/**
 * @param  array<array-key, mixed>  $values
 * @return list<array<array-key, mixed>>
 */
function exceptionReportsManifestObjectList(array $values, string $key): array
{
    $items = exceptionReportsManifestList($values, $key);
    $objects = [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            throw new RuntimeException(sprintf('Expected manifest list [%s] to contain arrays.', $key));
        }

        $objects[] = $item;
    }

    return $objects;
}

/**
 * @param  array<array-key, mixed>  $values
 * @return list<array<array-key, mixed>>
 */
function exceptionReportsScreenshotEntries(array $values): array
{
    if (array_is_list($values)) {
        $items = $values;
    } else {
        $items = exceptionReportsManifestList($values, 'entries');
    }

    $objects = [];

    foreach ($items as $item) {
        throw_unless(is_array($item), RuntimeException::class, 'Expected docs/screenshots.json entries to be objects.');

        $objects[] = $item;
    }

    return $objects;
}

/**
 * @param  array<array-key, mixed>  $values
 */
function exceptionReportsManifestString(array $values, string $key): string
{
    $value = $values[$key] ?? null;

    if (! is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('Expected manifest key [%s] to be a non-empty string.', $key));
    }

    return $value;
}

it('declares extension metadata and runtime provider', function (): void {
    $manifest = exceptionReportsJsonFileArray(__DIR__ . '/../../capell.json');
    $providers = exceptionReportsManifestArray($manifest, 'providers');
    $actions = exceptionReportsManifestArray($manifest, 'actions');
    $capabilities = exceptionReportsManifestList($manifest, 'capabilities');
    $marketplace = exceptionReportsManifestArray($manifest, 'marketplace');
    $traceability = exceptionReportsManifestArray($manifest, 'contributionTraceability');
    $contributes = exceptionReportsManifestObjectList($manifest, 'contributes');
    $healthChecks = exceptionReportsManifestObjectList($manifest, 'healthChecks');

    expect($manifest)
        ->toMatchArray([
            'name' => 'capell-app/exception-reports',
            'kind' => 'package',
            'capellApiVersion' => '^4.0',
            'product' => [
                'group' => 'Capell Operations',
                'tier' => 'free',
                'bundle' => 'operations',
            ],
        ])
        ->and($manifest['surfaces'])->toContain('shared')
        ->and($capabilities)->toBe([PackageCapability::TransactionalEmail->value])
        ->and(exceptionReportsManifestList($providers, 'runtime'))->toContain(ExceptionReportsServiceProvider::class)
        ->and($actions)->toHaveKey('reportExceptionByEmail', ReportExceptionByEmailAction::class)
        ->and($contributes)->toContain([
            'type' => 'health-check',
            'class' => ExceptionReportsHealthCheck::class,
        ])
        ->and($healthChecks[0]['class'])->toBe(ExceptionReportsHealthCheck::class)
        ->and(exceptionReportsManifestObjectList($marketplace, 'screenshots'))->not->toBeEmpty()
        ->and(exceptionReportsManifestList($traceability, 'deferredContributions'))->toBe([]);
});

it('validates the manifest and marketplace assets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = exceptionReportsJsonFileArray($packagePath . '/capell.json');
    $composer = exceptionReportsJsonFileArray($packagePath . '/composer.json');
    $marketplace = exceptionReportsManifestArray($manifest, 'marketplace');
    $screenshots = exceptionReportsManifestObjectList($marketplace, 'screenshots');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/exception-reports', $packagePath . '/capell.json');

    expect(class_implements(ExceptionReportsHealthCheck::class))->toContain(ChecksExtensionHealth::class);

    foreach ($screenshots as $screenshot) {
        expect($screenshot['alt'])->not->toBe('')
            ->and($screenshot['caption'])->not->toBe('')
            ->and(File::exists($packagePath . '/' . exceptionReportsManifestString($screenshot, 'path')))->toBeTrue();
    }

    $screenshotManifest = json_decode(File::get($packagePath . '/docs/screenshots.json'), associative: true, flags: JSON_THROW_ON_ERROR);

    throw_if(! is_array($screenshotManifest), RuntimeException::class, 'Expected docs/screenshots.json to contain a JSON object or list.');

    $screenshotEntries = exceptionReportsScreenshotEntries($screenshotManifest);

    expect($screenshotEntries)->not->toBeEmpty();

    foreach ($screenshotEntries as $screenshot) {
        expect(File::exists($packagePath . '/' . exceptionReportsManifestString($screenshot, 'path')))->toBeTrue();
    }
});
