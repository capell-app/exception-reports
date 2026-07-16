# Exception Reports

<!-- prettier-ignore-start -->

## What it does for you

Exception Reports registers with Laravel's exception handler and queues a sanitized email when an unhandled exception is reported. It can also, when explicitly enabled, send a signed payload to an approved HTTPS webhook endpoint. It does not store reports or add an admin inbox.

## Your screens

- **Your email or incident channel**: where the package delivers the sanitized report.
- **Application configuration**: where a developer sets the recipient, privacy controls, rate limits, digest, and optional webhook.

## What you can do

- Choose the email recipient through application configuration.
- Opt into selected request details, such as trace, IP address, user identity, or allow-listed route parameters.
- Configure rate limits and an optional grouped digest to reduce repeated email alerts.
- Enable an approved, signed HTTPS webhook for an incident channel.

## Where to find it

There is no Exception Reports admin page. A developer configures `config/capell-exception-reports.php` and the corresponding environment variables in the host application.

## Good to know

- Reports are delivery notifications, not a historical error log. Keep a separate monitoring or logging system if you need a searchable incident history.
- Sensitive values are redacted and unsafe markup is stripped before queued delivery. Trace, IP address, user identity, and route parameters are off by default.
- Webhooks require an HTTPS URL, an allow-listed public host, and a signing secret; failures in reporting are logged without replacing the original application error.

---

For how to use Exception Reports, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
