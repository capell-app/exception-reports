# Exception Reports - Improvement & Growth Plan

> Package: capell-app/exception-reports · Kind: package · Tier: free · Product group: Capell Operations · Bundle: operations · Status: Active

## 1. Snapshot

Exception Reports emails operators when Capell reports an unhandled exception. It registers a reportable exception handler, builds sanitized request/route/user/trace context, rate-limits duplicate/global reports, queues a Markdown mailable, exposes health diagnostics, and has focused feature/unit tests. The package is intentionally small, but its safety boundary is high-stakes: exception reports can include URLs, headers, route parameters, user identifiers, stack traces, tokens, and secrets, while the current sanitizer focuses mostly on stripping unsafe HTML/control characters.

## 2. Improvements (existing functionality)

1. **Add secret and token redaction to the sanitizer.** `ExceptionReportMailSanitizer` strips unsafe HTML but should also redact common secret keys and values: passwords, tokens, API keys, bearer headers, signed URLs, cookies, authorization headers, and query parameters such as `token`, `signature`, `password`, and `key`. Evidence: `src/Support/ExceptionReportMailSanitizer.php`, `src/Actions/ReportExceptionByEmailAction.php`. - **M**

2. **Stop swallowing reporter failures silently.** The service provider and Action catch `Throwable` and discard it. Add safe logging or an internal event with redacted context so operators can diagnose mail/reporting failures without recursion. Evidence: `src/Providers/ExceptionReportsServiceProvider.php`, `ReportExceptionByEmailAction::handle()`. - **M**

3. **Deepen health checks for queue/mail configuration.** Health checks recipient, provider, view, and rate limiter. Add checks for mailer availability, queue connection readiness, configured from address, and legacy recipient fallback behavior. Evidence: `src/Health/ExceptionReportsHealthCheck.php`, `config/capell-exception-reports.php`. - **S**

4. **Document operational safeguards.** README/overview should explain rate limits, recipient fallback, queue behavior, sanitizer guarantees, what is intentionally omitted, and how to test without causing recursive reports. Evidence: `README.md`, `docs/overview.md`, `docs/screenshots.json`. - **S**

## 3. Missing Features (gaps)

Capabilities declared: `transactional-email`, `exception-report-digests`, and `exception-report-webhooks`.

- **Shipped 2026-06-16: digest/grouping mode.** Repeated rate-limited exception signatures can be grouped into thresholded digest emails with count, threshold, window, signature, and grouped timestamp metadata.
- **Shipped 2026-06-16: optional webhook destination.** Teams can post sanitized JSON reports to configured incident endpoints with timeout enforcement, trace omission by default, and health diagnostics.
- **No admin inbox.** This package is intentionally console/shared only, but an admin inbox could support triage/history.
- **No attachment support.** The mailable returns no attachments, which is probably correct for privacy but should be explicit.

## 4. Issues / Risks

1. **Critical risk: exception context can contain secrets.** Recommended fix: key/value redaction for request/user/trace/URL/header payloads, plus tests. - **P1**

2. **Important risk: reporting failures disappear.** Recommended fix: non-recursive logging/event with safe context. - **P2**

3. **Important gap: health does not fully validate mail/queue readiness.** Recommended fix: mailer/queue diagnostics and recipient fallback coverage. - **P2**

4. **Improvement: docs should set clear privacy expectations.** Recommended fix: document sanitizer coverage and omitted data. - **P3**

## 5. Marketplace & Positioning

Exception Reports should be positioned as lightweight, privacy-aware exception alerting for Capell teams that are not ready for a full observability stack. For operators, emphasize rate limits, sanitized email and webhook context, and queued delivery. For developers, emphasize the reportable handler integration and sanitizer boundary.

**Current summary:** "Exception Reports emails operators when Capell reports an unhandled exception, including sanitized app, request, route, user, and stack-trace context that is safe to read in an email client."

**Improved summary:** "Privacy-aware exception reporting for Capell, with rate limits, queued email, optional sanitized webhooks, request/user/trace context, and install health diagnostics."

**Media status:** The extension card remains an SVG marketplace asset. The email preview is now a committed PNG captured from the rendered Markdown mailable, with fixture coverage proving secret redaction and unsafe diagnostic stripping remain visible in the preview.

**Cross-sell:** Diagnostics, Email Studio, Nightwatch configuration, Deployments, Site Monitor.

## 6. Prioritized Roadmap

| Item                                                      | Bucket | Effort | Impact | Section ref |
| --------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Redact common secrets/tokens/authorization data           | Done   | M      | High   | §2.1, §4.1  |
| Log or emit safe reporter failures without recursion      | Done   | M      | High   | §2.2, §4.2  |
| Add mailer/queue/from-address/fallback health diagnostics | Done   | S      | Medium | §2.3, §4.3  |
| Document rate limits, queue behavior, and sanitizer scope | Done   | S      | Medium | §2.4, §4.4  |
| Add digest/grouping mode                                  | Done   | M      | Medium | §3          |
| Add optional Slack/webhook destination                    | Done   | M      | Medium | §3, §5      |
| Add rendered email screenshot from real template          | Done   | S      | Low    | §5          |
| Add admin inbox/history surface                           | Later  | L      | Medium | §3          |
| Add escalation policies by environment/severity           | Later  | M      | Medium | §5          |

## 7. Verification

Implementation slices shipped the current Now rows. Re-run the package verification with:

```bash
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```

For sanitizer changes, include:

```bash
vendor/bin/pest packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php packages/exception-reports/tests/Feature/Health/ExceptionReportsHealthCheckTest.php --configuration=phpunit.xml
```

## 8. Completion Checklist

- [x] Package plan created from current code, manifest, docs, screenshots, and tests.
- [x] Comprehensive local review pass completed for provider, Action, mailable, sanitizer, health, docs, screenshots, and tests.
- [x] Capell audience pass completed for operators, developers, and buyers.
- [x] Approved implementation slices shipped.
- [x] Focused Exception Reports verification passed.
- [x] Package tests passed.
- [x] Repo preflight passed for changed files.
