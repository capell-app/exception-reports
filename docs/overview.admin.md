## What it does for you

Exception Reports registers with Laravel's exception handler and queues a sanitized email when an unhandled exception is reported. It can also, when explicitly enabled, send a signed payload to an approved HTTPS webhook endpoint. It does not store reports or add an admin inbox.

## Setup requirements

Set `EXCEPTION_REPORT_RECIPIENT` (which otherwise falls back to `MAIL_FROM_ADDRESS`), configure a valid mailer/from address, and keep a worker consuming the application's default queue. Accepted email and digest reports remain queued when workers are stopped. Run the package check in **Diagnostics** to verify the recipient, mailer, from address, queue connection, mail view, rate limiter, exception-handler integration, and optional webhook configuration.

Rate limiting and grouped digests require a shared, persistent cache when the application runs on more than one process or server. By default, the package accepts one report for the same exception signature every 15 minutes and ten reports globally per hour. A digest is off by default; when enabled, it queues a grouped email at each configured threshold of suppressed repeats within the digest window. It does not flush a final summary when the window ends.

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
- Route parameters are included only by exact allow-listed name. Enabling user identity, IP addresses, or traces can put personal data and application internals into email systems, incident channels, and their retention pipelines.
- Webhooks require an HTTPS URL, an allow-listed public host that resolves only to public addresses, and a signing secret. Redirects are not followed. Webhook delivery runs inline with short connection/request timeouts rather than through the queue, so a slow incident endpoint can delay exception reporting by up to those configured limits.
- Mail, cache, sanitization, and webhook failures are logged without replacing the original application error. A failed delivery is not persisted for later replay by this package.
