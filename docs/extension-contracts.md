# Extension and action examples

<!-- Maintained by scripts/generate-package-readmes.php -->

Use the action functions with records and Data objects supplied by your application.
They pass each argument to the package operation and return its result.

These adapters show container registration. Use the owning package registry when
a contract requires contributor discovery.

Contract adapters wrap an existing implementation. Call their registration function
from your service provider with that implementation; tagged contracts keep their declared tag.
Resolve the backend by its concrete class before registration so the replacement contract
does not resolve itself. Static contract metadata uses one backend class per adapter.

## Action `recordExceptionReport`

<!-- example: action recordExceptionReport -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\ExceptionReports;

function runRecordExceptionReport(\Throwable $exception, ?\Illuminate\Http\Request $request = null): void
{
    \Capell\ExceptionReports\Actions\RecordExceptionReportAction::run($exception, $request);
}
```

## Action `reportExceptionByEmail`

<!-- example: action reportExceptionByEmail -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\ExceptionReports;

function runReportExceptionByEmail(\Throwable $exception): void
{
    \Capell\ExceptionReports\Actions\ReportExceptionByEmailAction::run($exception);
}
```
