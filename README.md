# Exception Reports

<!-- prettier-ignore-start -->

## What This Plugin Adds

Exception Reports is an **Available**, **Schema-owning** Capell package in the **Capell Operations** product group. It ships as `capell-app/exception-reports` and extends these surfaces: console, shared.

Exception Reports queues sanitized unhandled-exception reports to configured email recipients and optional signed webhook endpoints, with rate limiting and grouped digests.

Operators receive a sanitized email or webhook payload for triage. The package has no admin resource; recipients, privacy options, and delivery behavior are configured at application level.

Evidence: [`capell.json`](capell.json), [`config/capell-exception-reports.php`](config/capell-exception-reports.php), [`src/Actions/ReportExceptionByEmailAction.php`](src/Actions/ReportExceptionByEmailAction.php), [`src/Actions/SendExceptionReportWebhookAction.php`](src/Actions/SendExceptionReportWebhookAction.php), [`docs/screenshots.json`](docs/screenshots.json), [`src/Mail/UnhandledExceptionReported.php`](src/Mail/UnhandledExceptionReported.php).

Status details:

- Status: Available
- Tier: free
- Bundle: operations
- Composer package: `capell-app/exception-reports`
- Namespace: `Capell\ExceptionReports`
- Theme key: not applicable

## Why It Matters

**For developers:** A typed report boundary and sanitizer keep request secrets out of queued payloads, while trace, identity, IP, and route-parameter details remain opt-in.

**For teams:** Operational failures reach the configured owner with enough context to start triage, and repeated signatures can be rate limited or summarized instead of producing duplicate alerts.

Evidence: [`src/Data/ExceptionReportData.php`](src/Data/ExceptionReportData.php), [`src/Support/ExceptionReportMailSanitizer.php`](src/Support/ExceptionReportMailSanitizer.php), [`config/capell-exception-reports.php`](config/capell-exception-reports.php), [`tests/Feature/ExceptionEmailReportingTest.php`](tests/Feature/ExceptionEmailReportingTest.php), [`src/Actions/QueueRateLimitedExceptionDigestAction.php`](src/Actions/QueueRateLimitedExceptionDigestAction.php), [`src/Providers/ExceptionReportsServiceProvider.php`](src/Providers/ExceptionReportsServiceProvider.php), [`tests/Feature/Health/ExceptionReportsHealthCheckTest.php`](tests/Feature/Health/ExceptionReportsHealthCheckTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Exception Reports extension card](docs/screenshots/extension-card.svg)

- Exception Reports extension card (marketplace, required evidence).
- Illustrative exception reports rendered email preview preview (frontend, supplementary documentation fixture).

## Technical Shape

### Service providers

- `Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider`

### Config files

- `packages/exception-reports/config/capell-exception-reports.php`

### Migrations

- `packages/exception-reports/database/migrations/2026_08_22_000001_create_exception_reports_table.php`

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

### Health checks

- `Capell\ExceptionReports\Health\ExceptionReportsHealthCheck`

### Blade views

- `packages/exception-reports/resources/views/mail/reported.blade.php`


## Data Model

- Required tables: `exception_reports`.
- Models: `ExceptionReport`.
- Migration files: `2026_08_22_000001_create_exception_reports_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap: migrations and manifest contributions do not prove a cascade, pruning command, or timed retention policy.

## Install Impact

- Required packages: `capell-app/core`.
- Admin navigation: no admin page or resource contribution is declared.
- Admin/editor extensions: none declared.
- Permissions: no package permission declarations or Shield gates detected; host access rules still apply.
- Public routes: none declared.
- Database changes: package migrations are declared.
- Config: `config/capell-exception-reports.php`.
- Settings: no package settings declared.
- Queues or schedules: none declared.
- Cache tags: none declared.
- Commands: none declared.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/core`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-exception-reports.php`.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |

## Quick Start

1. Install the package: `composer require capell-app/exception-reports`.
2. Open the package detail or install-intent surface and confirm the Exception Reports extension card is present.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-exception-reports.php`](config/capell-exception-reports.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Diagnostics](../diagnostics/README.md).
- Focused tests: `vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
