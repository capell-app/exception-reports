# Using Exception Reports

This guide is for the developer or operator who configures exception delivery for a Capell host application. Exception Reports registers a Laravel exception reporter, queues a sanitized email, and can optionally send a signed webhook payload. It does not create an admin inbox, settings screen, or stored error history.

## Configuring Exception Reports

### How to configure email delivery

1. Publish or review `config/capell-exception-reports.php` in the host application.
2. Set `EXCEPTION_REPORT_RECIPIENT` to the monitored mailbox. The package falls back to `MAIL_FROM_ADDRESS` when it is not set.
3. Keep `CAPELL_EXCEPTION_REPORTS_ENABLED=true` to register delivery. Set it to `false` when you need to disable reporting without removing the package.
4. Confirm that the application's mail queue is operating: reports are queued rather than sent inline with the failing request.

### How to configure an incident webhook

1. Set `CAPELL_EXCEPTION_REPORTS_WEBHOOK_ENABLED=true`.
2. Set an HTTPS `CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL`, then add its public hostname to `CAPELL_EXCEPTION_REPORTS_WEBHOOK_ALLOWED_HOSTS`.
3. Set `CAPELL_EXCEPTION_REPORTS_WEBHOOK_SIGNING_SECRET` and verify the receiving service validates the `X-Capell-Event-Id`, `X-Capell-Timestamp`, and `X-Capell-Signature` headers.
4. Keep webhook trace inclusion off unless the incident channel has an approved need for it.

### How to choose diagnostic detail

1. Leave IP address, user identity, stack traces, and route parameters disabled unless they are necessary for triage.
2. Enable only the relevant `CAPELL_EXCEPTION_REPORTS_INCLUDE_*` variables.
3. If route parameters are needed, set `CAPELL_EXCEPTION_REPORTS_ROUTE_PARAMETER_ALLOWLIST` to the exact parameter names; other route parameters stay out of the report.
4. The sanitizer strips unsafe markup and redacts secret-like values, authorization values, cookies, tokens, and email addresses before delivery. This is a safety boundary, not permission to include unnecessary context.

### How to triage a report

1. Open the delivered email or incident-channel payload; there is no report record inside Capell admin.
2. Start with the exception class, message, source, environment, request method, route, and timestamp.
3. Forward the sanitized delivery to the developer who owns the affected route or command. If trace collection is disabled, use your normal application logs for deeper investigation.

### How to handle a flood of notifications

1. Keep signature and global rate limits enabled. Their attempts and decay windows are configured through `CAPELL_EXCEPTION_REPORTS_SIGNATURE_*` and `CAPELL_EXCEPTION_REPORTS_GLOBAL_*` variables.
2. Enable `CAPELL_EXCEPTION_REPORTS_DIGEST_ENABLED=true` to queue grouped email digests for suppressed repeated exception signatures.
3. Set the digest threshold and window to match the urgency of your application, then test the change in a safe environment.

## Rolling out Exception Reports (for owners)

### Turn on first

- **A monitored recipient and working queue.** Configure both before you rely on the package, so a real error reaches a person rather than a silent queue failure.

### Add when needed

| Need                                     | What to use                                              |
| ---------------------------------------- | -------------------------------------------------------- |
| Make sure errors reach a person | Set `EXCEPTION_REPORT_RECIPIENT` and verify the mail queue |
| Post errors into a chat or other tool | Configure the signed HTTPS webhook and its host allow-list |
| Avoid being flooded by a repeating error | Configure rate limits and enable the optional email digest |
| Investigate a detailed failure | Use the delivered report with normal application logs |

### Who does what

| Role       | What they do                                                              |
| ---------- | ------------------------------------------------------------------------- |
| Operator | Configures the recipient, webhook, privacy, and rate-limit values in the host application |
| Developer | Reads the delivered report and application logs, then fixes the cause |

## Troubleshooting

| What you see                           | What it means                                       | What to do                                                      |
| -------------------------------------- | --------------------------------------------------- | --------------------------------------------------------------- |
| No report arrives | Reporting is disabled, no recipient is configured, or the mail queue is not running | Check `CAPELL_EXCEPTION_REPORTS_ENABLED`, `EXCEPTION_REPORT_RECIPIENT`, and the host queue worker |
| The webhook receives nothing | Webhook delivery is disabled or its HTTPS/allow-list/signing configuration is invalid | Check the webhook environment variables and application logs; webhook failures never replace the original exception |
| You get too many emails | A noisy error is firing often | Adjust signature/global rate limits or enable the digest in application configuration |
| A report has less context than expected | The relevant privacy option is off by default | Enable only the necessary opt-in context, preferably in a safe environment first |
| A report contains `[redacted]` | The sanitizer removed a secret-like value or personal data | This is expected; use controlled application logs if deeper diagnostics are needed |
