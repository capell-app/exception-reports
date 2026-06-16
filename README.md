# Exception Reports

<!-- prettier-ignore-start -->

## What This Plugin Adds

Exception Reports is an **Available**, **No schema impact** Capell package in the **Capell Operations** product group. It ships as `capell-app/exception-reports` and extends these surfaces: console, shared.

Exception Reports emails operators when Capell reports an unhandled exception, with optional sanitized webhook delivery for incident channels. Reports include sanitized app, request, route, user, and stack-trace context that is safe to read in an email client.

After install, the package is operated through console commands or background maintenance hooks.

Status details:

- Status: Available
- Tier: free
- Bundle: operations
- Composer package: `capell-app/exception-reports`
- Namespace: `Capell\ExceptionReports`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Email alerting for unhandled Capell exceptions, with request, route, user, and stack-trace context sanitized for safe operator triage.

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

- Exception Reports extension card (marketplace, required).
- Exception Reports email preview (email, required).

## Technical Shape

- Service providers: `Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider`.
- Config files: `packages/exception-reports/config/capell-exception-reports.php`.
- Actions: `ReportExceptionByEmailAction`.
- Manifest contributions: `health-check: Capell\ExceptionReports\Health\ExceptionReportsHealthCheck`.
- Health checks: `Capell\ExceptionReports\Health\ExceptionReportsHealthCheck`.
- Blade views: `packages/exception-reports/resources/views/mail/reported.blade.php`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: no admin surface declared.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: none declared.
- Commands: none declared.

## Operational Safeguards

- Reports are rate-limited so repeated exception storms do not create unbounded email or webhook volume.
- Optional digest mode groups repeated rate-limited exception signatures and queues a digest email every configured threshold.
- Delivery is queued through Laravel mail when the host queue is configured; synchronous hosts still use the same sanitized mailable boundary.
- Optional webhook delivery posts sanitized JSON to `CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL` with a timeout. Stack traces are omitted from webhook payloads unless `CAPELL_EXCEPTION_REPORTS_WEBHOOK_INCLUDE_TRACE=true`.
- Recipient configuration should be explicit. The health check reports missing recipients and the fallback path so operators know where alerts are going.
- Sanitization redacts common secret-bearing keys and values, including passwords, tokens, API keys, cookies, authorization headers, signatures, sessions, and signed URL query parameters.
- The email intentionally omits attachments and raw request bodies. Add destination-specific integrations later only if they can preserve the same redaction boundary.
- Reporter failures are logged with safe context instead of being re-reported recursively.

## Common Pitfalls

- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Test report delivery with a controlled exception and a non-production recipient; do not paste raw production payloads into docs, tickets, or screenshots.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Health reports mail configuration warnings | Missing recipient, sender, queue, or mailer config | Run the package health check and inspect `capell-exception-reports` config | Set an explicit recipient/from address and confirm queue/mail transport readiness |
| Health reports webhook configuration warnings | Webhook delivery is enabled without an HTTP(S) endpoint | Check `CAPELL_EXCEPTION_REPORTS_WEBHOOK_ENABLED` and `CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL` | Set an HTTPS webhook endpoint or disable webhook delivery |
| Operators only receive the first email during a repeated exception storm | Signature rate limiting is suppressing duplicates | Check `capell-exception-reports.digest.enabled`, `threshold`, and `window_seconds` | Enable digest mode when grouped repeated-failure emails are useful |
| Report email contains redacted placeholders | Sanitizer detected secret-bearing keys or values | Inspect the originating request context without copying secrets | Keep the redaction; add structured, non-secret context at the source if operators need more detail |

## Quick Start

1. Install the package: `composer require capell-app/exception-reports`.
2. Run the required setup: no package migrations are declared; clear cached config and routes if the host app uses caches.
3. Verify the package provider is registered and the related frontend, command, or extension point is active.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Diagnostics](../diagnostics/README.md).
- Focused tests: `vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
