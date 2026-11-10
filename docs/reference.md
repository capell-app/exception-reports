# Exception Reports reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/exception-reports`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Exception Reports is an **Available**, **Schema-owning** Capell package in the **Capell Operations** product group. It ships as `capell-app/exception-reports` and extends these surfaces: console, shared.

- Status: Available
- Tier: free
- Bundle: operations
- Composer package: `capell-app/exception-reports`
- Namespace: `Capell\ExceptionReports`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\ExceptionReports\Providers\MigrationMetadataServiceProvider`
- `Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider`

### Config files

- `packages/exception-reports/config/capell-exception-reports.php`

### Migrations

- `packages/exception-reports/database/migrations/2026_08_22_000001_create_exception_reports_table.php`
- `packages/exception-reports/database/migrations/2026_09_29_120000_remove_implicit_timestamp_updates_from_exception_reports.php`

### Models

- `ExceptionReport`

### Actions

- `QueueRateLimitedExceptionDigestAction`
- `RecordExceptionReportAction`
- `ReportExceptionByEmailAction`
- `SendExceptionReportWebhookAction`

### Data objects

- `ExceptionReportData`
- `ResolvedExceptionReportWebhookEndpointData`

### Manifest action API

- `recordExceptionReport: Capell\ExceptionReports\Actions\RecordExceptionReportAction`
- `reportExceptionByEmail: Capell\ExceptionReports\Actions\ReportExceptionByEmailAction`

### Manifest contributions

- `health-check: Capell\ExceptionReports\Health\ExceptionReportsHealthCheck`
- `migration: Capell\ExceptionReports\Manifest\ExceptionReportsMigrationsContribution`

### Health checks

- `Capell\ExceptionReports\Health\ExceptionReportsHealthCheck`

### Blade views

- `packages/exception-reports/resources/views/mail/reported.blade.php`


## Data Model

- Required tables: `exception_reports`.
- Models: `ExceptionReport`.
- Migration files: `2026_08_22_000001_create_exception_reports_table.php`, `2026_09_29_120000_remove_implicit_timestamp_updates_from_exception_reports.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap: migrations and manifest contributions do not prove a cascade, pruning command, or timed retention policy.

<!-- prettier-ignore-end -->
