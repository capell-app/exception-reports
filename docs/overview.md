# Exception Reports

## Status

- Tier: free
- Bundle: operations
- Certification: first-party
- Surfaces: shared runtime, console/Diagnostics health

## Buyer Value

Exception Reports gives Capell operators a direct email signal when an unhandled exception is reported. The email includes the exception class, message, app environment, source route or action, request identifiers, route parameters, current user, and stack trace.

## Safety

Diagnostic values are sanitized before rendering in Markdown mail. Unsafe HTML and control characters are stripped, affected fields are listed, and long stack traces are wrapped for email clients. Signature and global rate limits reduce repeated failure floods.

## Setup

Set `EXCEPTION_REPORT_RECIPIENT` or publish/override `capell-exception-reports.recipient`. Set `CAPELL_EXCEPTION_REPORTS_ENABLED=false` to disable email reports without removing the package.

## Screenshot Notes

The committed Marketplace assets are illustrative SVG previews. Replace them with real admin/mail captures when a demo harness can generate deterministic exception reports.
