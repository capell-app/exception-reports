# Exception Reports

Exception Reports queues sanitized email reports when a Capell app reports an unhandled exception, giving operators enough request and stack context to triage the fault without exposing unsafe HTML or flooding inboxes.

## At A Glance

| Field                         | Value                                                                                                                       |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Composer package              | `capell-app/exception-reports`                                                                                              |
| Namespace                     | `Capell\ExceptionReports\`                                                                                                  |
| Product group / tier / bundle | Capell Operations / free / operations                                                                                       |
| Surfaces                      | shared runtime, queued mail, Diagnostics health                                                                             |
| Service provider              | `Capell\ExceptionReports\Providers\ExceptionReportsServiceProvider`                                                         |
| Dependencies                  | `capell-app/core`                                                                                                           |
| Config                        | `config/capell-exception-reports.php`                                                                                       |
| Key classes                   | `ReportExceptionByEmailAction`, `UnhandledExceptionReported`, `ExceptionReportMailSanitizer`, `ExceptionReportsHealthCheck` |

## Why It Helps Your Capell Workflow

Owners get a direct failure signal when production raises an exception, without waiting for a full observability stack to be wired.

Operators receive the route/action, request metadata, app environment, current user summary, exception message, file, line, and stack trace in a queued Markdown email.

Developers get an Action-driven reporter and Diagnostics health check that can be tested without putting exception-report logic into controllers, resources, or public views.

## What It Adds

- Registers a Laravel exception `reportable` callback after the host `ExceptionHandler` resolves.
- Queues `UnhandledExceptionReported` mail to `capell-exception-reports.recipient`.
- Sanitizes every report field with `ExceptionReportMailSanitizer` before rendering the email.
- Applies per-signature and global rate limits through Laravel's `RateLimiter`.
- Exposes Diagnostics checks for provider registration, recipient config, mail rendering, and rate-limiter readiness.
- Supports `CAPELL_EXCEPTION_REPORTS_ENABLED=false` to disable reporting without uninstalling the package.

## Boundaries

Exception Reports owns email reporting for unhandled exceptions. It does not replace host logging, error tracking, incident response, or queue monitoring.

The package must not render anything into public frontend output. It should not include request bodies, session values, cookies, secrets, tokens, or arbitrary model payloads in email reports.

## Runtime Surface

- `src/Providers/ExceptionReportsServiceProvider.php` binds the sanitizer and registers the exception reporter.
- `src/Actions/ReportExceptionByEmailAction.php` builds the report payload, checks recipient/rate limits, and queues mail.
- `src/Mail/UnhandledExceptionReported.php` renders the sanitized Markdown mail and implements `ShouldQueue`.
- `src/Support/ExceptionReportMailSanitizer.php` strips unsafe values before email rendering.
- `src/Health/ExceptionReportsHealthCheck.php` powers Diagnostics readiness checks.
- `config/capell-exception-reports.php` controls enablement, recipient, and rate limits.

## Configuration

Set the recipient in the host Capell app:

```dotenv
EXCEPTION_REPORT_RECIPIENT=ops@example.test
```

Optional controls:

```dotenv
CAPELL_EXCEPTION_REPORTS_ENABLED=true
CAPELL_EXCEPTION_REPORTS_SIGNATURE_ATTEMPTS=1
CAPELL_EXCEPTION_REPORTS_SIGNATURE_DECAY_SECONDS=900
CAPELL_EXCEPTION_REPORTS_GLOBAL_ATTEMPTS=10
CAPELL_EXCEPTION_REPORTS_GLOBAL_DECAY_SECONDS=3600
```

`EXCEPTION_REPORT_RECIPIENT` falls back to `MAIL_FROM_ADDRESS` through the package config. If neither value is configured, the Action returns without sending mail.

## Docs

- [Overview](docs/overview.md)
- [Screenshot manifest](docs/screenshots.json)

## Testing

Run the package tests from the monorepo root:

```bash
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```

## Troubleshooting

| Symptom                                           | Likely cause                                        | Check                                                                                                           | Fix                                                                                                            |
| ------------------------------------------------- | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| No email is queued for an exception               | Reporting is disabled or no recipient is configured | Check `CAPELL_EXCEPTION_REPORTS_ENABLED`, `EXCEPTION_REPORT_RECIPIENT`, and `MAIL_FROM_ADDRESS` in the host app | Enable reporting and configure a recipient, then rerun the focused package test or trigger a controlled report |
| Only the first repeated exception sends mail      | Signature rate limit is suppressing duplicates      | Check `CAPELL_EXCEPTION_REPORTS_SIGNATURE_ATTEMPTS` and `CAPELL_EXCEPTION_REPORTS_SIGNATURE_DECAY_SECONDS`      | Increase the signature attempts or wait for the decay window                                                   |
| Many unrelated exceptions stop sending            | Global rate limit has been reached                  | Check `CAPELL_EXCEPTION_REPORTS_GLOBAL_ATTEMPTS` and `CAPELL_EXCEPTION_REPORTS_GLOBAL_DECAY_SECONDS`            | Raise the global limit for noisy environments and keep queue monitoring enabled                                |
| Diagnostics reports mail rendering as unavailable | Mail view or sanitizer binding failed               | Run the package Diagnostics health check in the host app                                                        | Confirm the service provider is discovered and the package views/translations are published or loadable        |
