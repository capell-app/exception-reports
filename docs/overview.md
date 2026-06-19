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

Screenshot contract: `screenshots.json`.

- Exception Reports extension card (marketplace, required).
- Exception Reports email preview (email, required).

## Screenshot Evidence

These captures are the package-owned visual contract for the admin pages, public pages, actions, workflows, and feature surfaces described above. Keep this section aligned with `docs/screenshots.json` whenever the package surface changes.

### Exception Reports extension card

![Exception Reports extension card](screenshots/extension-card.svg)

- Surface: marketplace · Target: extension-card.
- Documents: An operator reviews the extension card before enabling exception email reporting.
- Capture notes: Committed Marketplace card preview for Exception Reports.

### Exception Reports rendered email preview

![Exception Reports rendered email preview](screenshots/exception-email-preview.png)

- Surface: email · Target: exception-email-preview.
- Documents: An operator verifies the report email includes useful context without unsafe diagnostic markup.
- Capture notes: Committed PNG captured from the rendered sanitized exception report mailable.

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

- Exception alerts are rate-limited to protect operators during repeated failures.
- Optional digest mode groups repeated rate-limited exception signatures and sends a digest email every configured threshold.
- Mail delivery uses the host mail/queue configuration; health diagnostics check the recipient, from address, mailer, and queue readiness.
- Optional webhook delivery posts sanitized JSON to `CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL` with a timeout. Stack traces are omitted unless `CAPELL_EXCEPTION_REPORTS_WEBHOOK_INCLUDE_TRACE=true`.
- Sanitization covers common secret-bearing values such as passwords, API keys, bearer tokens, cookies, sessions, signatures, signed URLs, and authorization headers.
- The mailable intentionally omits attachments and raw request bodies so report payloads stay small and privacy-aware.
- Reporter failures are logged once with redacted context and are not fed back into the exception reporter.

## Common Pitfalls

- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Use a disposable recipient when testing exception delivery, and avoid using live secrets in reproduction payloads.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| No exception email arrives | Missing recipient, sender, queue worker, or mail transport | Check health diagnostics and host mail logs | Configure an explicit recipient/from address and start the queue worker if the host queues mail |
| Webhook alerts do not send | Webhook disabled, missing URL, invalid URL, or remote endpoint failure | Check health diagnostics, `CAPELL_EXCEPTION_REPORTS_WEBHOOK_*` config, and host logs | Configure an HTTPS endpoint and keep the timeout low enough for exception paths |
| Repeated failures only send one normal alert | Signature rate limiting is suppressing duplicates | Check `capell-exception-reports.digest.enabled`, `threshold`, and `window_seconds` | Enable digest mode if operators need grouped repeated-failure emails |
| Operators need more context than the email shows | Sanitizer or omission policy removed sensitive payloads | Review the source exception and non-secret route/request metadata | Add safe application context before throwing; do not weaken the sanitizer for raw secrets |

## Quick Start

1. Install the package: `composer require capell-app/exception-reports`.
2. Run the required setup: no package migrations are declared; clear cached config and routes if the host app uses caches.
3. Verify the package provider is registered and the related frontend, command, or extension point is active.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Diagnostics](../../diagnostics/README.md).
- Focused tests: `vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
