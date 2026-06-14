# Exception Reports Improvement Plan

Primary readers: Capell package developers, Laravel integrators, and operators who need predictable exception email reporting without leaking sensitive request data.

This is a first-phase plan pass only. It records the current package shape, confirmed risks, and implementation order. No runtime code changes are included in this pass.

## Snapshot

- `capell-app/exception-reports` is a first-party Capell Operations package with no database schema, no admin resources, no public routes, and one runtime provider.
- `ExceptionReportsServiceProvider` registers `ExceptionReportMailSanitizer`, then hooks Laravel's exception handler with a `reportable()` callback that resolves `ReportExceptionByEmailAction`.
- `ReportExceptionByEmailAction` builds an array report with app, environment, exception, request, route, user, and stack-trace context, rate limits duplicate/global reports, and queues `UnhandledExceptionReported`.
- `UnhandledExceptionReported` renders `capell-exception-reports::mail.reported` and sanitizes the report lazily when the envelope/content is rendered.
- `ExceptionReportMailSanitizer` currently strips unsafe HTML, control characters, Markdown table separators, and suspicious markup from scalar/report fields. It does not redact URL query secrets, signed URL signatures, bearer-like tokens, route parameter secrets, or secret-looking exception messages.
- Package-local tests cover queued mail delivery, handler failure masking, request/route context, HTML/control-character sanitization, email wrapping, rate limiting, manifest validation, marketplace assets, and health checks.
- Manifest and docs are mostly aligned with the installed surface: runtime provider, health check contribution, no schema, no permissions, no routes, and marketplace screenshots/assets. The copy overstates "safe" sanitization until secret redaction is implemented.

## Improvements

- Add secret redaction before queue serialization and mail rendering. The queued mailable payload should never contain raw signed URL signatures, tokenized route values, credential-like query parameters, API keys, passwords, authorization codes, or reset/preview tokens.
- Split safety concerns into explicit behaviors:
  - HTML/control-character sanitization protects the email client.
  - Secret redaction protects operators, mailbox search, failed-job storage, queue payloads, and forwarded emails.
- Introduce a small typed report boundary under `src/Data/` so the Action, Mailable, sanitizer/redactor, Blade view, and tests stop sharing unstructured arrays.
- Keep rate limiting and failure masking, but add tests for disabled reporting, missing recipients, and legacy recipient fallback so the operational contract is explicit.
- Tighten health diagnostics to validate recipient shape and mail-render readiness without accidentally sending email.
- Replace generic generated docs copy with operator-focused setup notes: what gets captured, what gets redacted, how rate limits work, how to disable reporting per environment, and what the package is not trying to replace.
- Update manifest security metadata after redaction lands, especially `security.sensitiveData.redactedOutputClasses`, marketplace copy, and public claims that say reports are safe to read.

## Missing Features

- Configurable sensitive-key list for query strings, route parameters, nested arrays, headers, and exception message/trace text. Defaults should include `token`, `signature`, `expires`, `password`, `secret`, `key`, `api_key`, `access_token`, `refresh_token`, `code`, `auth`, `authorization`, `bearer`, `preview`, `reset`, and `signed`.
- Redaction metadata in the safe report, separate from the existing unsafe-markup metadata. Operators should be told fields were redacted without seeing the original value.
- Per-field capture controls for high-sensitivity environments: user email/name, IP address, referer, browser, route parameters, URL query strings, and stack traces.
- A test-mail or preview command is absent. Operators can only prove rendering through package tests or by forcing an exception.
- Documentation for install/config is thin and includes an inaccurate line that the package is "operated through console commands" even though no package commands are declared.
- No direct test proves `CAPELL_EXCEPTION_REPORTS_ENABLED=false` prevents queued mail.
- No direct test proves the legacy `services.exception_reports.to` recipient fallback still works.
- No direct test proves reports are sanitized/redacted before the mailable is serialized to a queue payload.

## Issues/Risks

- **P1: Tokenized public route secrets can leak through exception emails and queued mail payloads.** `ReportExceptionByEmailAction` captures `Request::fullUrl()` and raw route parameters in `request.url` and `request.route_parameters`. The sanitizer strips markup/control characters, but it does not redact signed URL query values or token route parameter values. Existing tests assert route parameters are preserved raw. Plan the fix before any broader feature work.
- **P1: Lazy sanitization leaves the queued mailable payload raw.** The Action queues `new UnhandledExceptionReported($report)` before sanitization, and the Mailable resolves `safeReport()` only when rendering the envelope/content. Even if email output becomes safer, failed-job rows, queue backends, serialized payload logs, or debugging dumps can still contain raw request secrets unless the constructor or Action stores only the safe report.
- **P2: Array-shaped report boundaries make safety hard to prove.** The report array is built in the Action, transformed in the sanitizer, passed through the Mailable, and read by Blade. A typed `ExceptionReportData` boundary would make required fields, redaction metadata, and safe output rules testable.
- **P2: Docs and marketplace copy overclaim safety.** README, docs overview, composer description, and manifest copy describe reports as sanitized/safe, but current behavior is HTML-focused sanitization rather than secret-aware redaction.
- **P2: Health checks do not validate operational readiness deeply enough.** The health check verifies provider, recipient presence, renderability, rate limiter, and handler shape. It does not validate recipient format, disabled reporting state, queue connection viability, or redaction readiness.
- **P3: Operator setup guidance is sparse.** The docs do not show env vars, rate-limit defaults, disable behavior, privacy tradeoffs, or expected first-response workflow for Laravel integrators/operators.

## Marketplace & Positioning

- Position the package as a lightweight Capell operations signal: "email the right operator with enough redacted context to start triage." Do not position it as an APM, log aggregator, uptime monitor, or incident management system.
- Keep the Capell package story clear for developers: it installs as a Composer package, registers through a service provider and manifest, contributes a health check, and stays removable without schema cleanup.
- For Laravel integrators, explain the runtime boundary: the package listens to Laravel's exception handler, queues a mailable, rate limits duplicate/global failures, and uses config/env vars rather than admin screens.
- For operators, lead with concrete outcomes: exception class, environment, source route/action/path, request ID, selected user context, and stack trace after unsafe markup is stripped and secrets are redacted.
- Until P1 redaction is implemented, avoid stronger claims than "unsafe diagnostic markup is stripped." After P1, update marketplace copy to say "secrets are redacted before the report is queued and rendered."
- Marketplace assets already exist for card/email preview plus hero/thumbnail imagery. After redaction copy changes, refresh the email preview if it visually claims secret-safe behavior.

## Prioritized Roadmap

### 1. P1 - Redact secrets before queueing or rendering

Files:

- Modify `packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php`
- Modify `packages/exception-reports/src/Support/ExceptionReportMailSanitizer.php`
- Modify `packages/exception-reports/src/Mail/UnhandledExceptionReported.php`
- Modify `packages/exception-reports/resources/views/mail/reported.blade.php`
- Modify `packages/exception-reports/resources/lang/en/mail.php`
- Modify `packages/exception-reports/config/capell-exception-reports.php`

Implementation notes:

- Add failing tests that generate exception reports from URLs containing `?signature=`, `?expires=`, `?token=`, `?api_key=`, and `?password=`.
- Add failing tests for route parameters such as `{preview_token}`, `{signedDownloadToken}`, and nested array route values, while proving safe route parameters such as `{package}` remain visible.
- Assert against both rendered HTML and `$mail->report` so redaction is proven before queue serialization, not only during Markdown rendering.
- Use a stable placeholder such as `[redacted]`.
- Add `redaction.sensitive_keys` and `redaction.placeholder` config with secure defaults so integrators can extend key matching without editing package code.
- Track redacted paths separately from unsafe-markup paths, for example `redacted.detected` and `redacted.paths`.
- Keep the existing unsafe-markup warning behavior for stripped HTML/control characters.
- Make sanitizer calls idempotent so direct mailable tests and queued reports can both pass through the same safety layer without double-changing values.

Verification:

```bash
vendor/bin/pest packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php --configuration=phpunit.xml
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```

### 2. P2 - Add a typed report boundary

Files:

- Create `packages/exception-reports/src/Data/ExceptionReportData.php`
- Create `packages/exception-reports/src/Data/ExceptionRequestContextData.php`
- Create `packages/exception-reports/src/Data/ExceptionUserContextData.php`
- Modify `packages/exception-reports/src/Actions/ReportExceptionByEmailAction.php`
- Modify `packages/exception-reports/src/Mail/UnhandledExceptionReported.php`
- Modify `packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php`
- Update root Composer autoload only if new namespaces require it beyond the existing `Capell\ExceptionReports\` mapping

Implementation notes:

- Keep the data layer small: one top-level report data class, one request context class, and one optional user context class.
- Use package-local final readonly data classes with `fromArray()` and `toArray()` methods so this package does not need a new direct dependency for a small mail payload.
- Make the safe/redacted report the only shape the Mailable stores publicly.
- Keep Blade reads simple and defensive, but stop making Blade responsible for knowing raw report structure.

Verification:

```bash
vendor/bin/pest packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php --configuration=phpunit.xml
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```

### 3. P2 - Strengthen operational controls and diagnostics

Files:

- Modify `packages/exception-reports/config/capell-exception-reports.php`
- Modify `packages/exception-reports/src/Actions/ReportExceptionByEmailAction.php`
- Modify `packages/exception-reports/src/Health/ExceptionReportsHealthCheck.php`
- Modify `packages/exception-reports/resources/lang/en/package.php`
- Modify `packages/exception-reports/tests/Feature/Health/ExceptionReportsHealthCheckTest.php`
- Modify `packages/exception-reports/tests/Feature/ExceptionEmailReportingTest.php`

Implementation notes:

- Add config for optional capture of user context, IP address, referer, browser, query strings, route parameters, and stack traces.
- Add tests for disabled reporting, empty package recipient, malformed recipient, legacy recipient fallback, and rate-limit config coercion.
- Keep failure masking in place so exception reporting never becomes the cause of an application error.
- Do not send live mail from health checks; health checks should validate configuration and rendering only.

Verification:

```bash
vendor/bin/pest packages/exception-reports/tests/Feature/Health/ExceptionReportsHealthCheckTest.php --configuration=phpunit.xml
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```

### 4. P2 - Align docs, manifest, and marketplace copy

Files:

- Modify `packages/exception-reports/README.md`
- Modify `packages/exception-reports/docs/README.md`
- Modify `packages/exception-reports/docs/overview.md`
- Modify `packages/exception-reports/capell.json`
- Modify `packages/exception-reports/docs/screenshots.json` only if screenshot semantics change
- Modify marketplace assets only if their visible copy changes
- Modify `packages/exception-reports/tests/Unit/ManifestRequirementsTest.php` if manifest assertions change

Implementation notes:

- Replace generated placeholder lines such as "operated through console commands" and "Docs gap" with actual package behavior.
- Document every env var in `config/capell-exception-reports.php`.
- Document the distinction between stripped unsafe markup and redacted secrets.
- Update `security.sensitiveData.redactedOutputClasses` after redaction is implemented.
- Keep positioning focused on Composer-installed Laravel packages, explicit extension points, and operator triage.

Verification:

```bash
vendor/bin/pest packages/exception-reports/tests/Unit/ManifestRequirementsTest.php --configuration=phpunit.xml
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
composer docs:check
```

### 5. P3 - Add operator preview and smoke workflow

Files:

- Create `packages/exception-reports/src/Commands/SendExceptionReportTestEmailCommand.php`
- Modify `packages/exception-reports/src/Providers/ExceptionReportsServiceProvider.php`
- Modify `packages/exception-reports/capell.json`
- Modify `packages/exception-reports/resources/lang/en/package.php`
- Add focused tests under `packages/exception-reports/tests/Feature/`

Implementation notes:

- Add this only after redaction and docs are fixed.
- The command should send a synthetic, already-redacted sample report to the configured recipient.
- Keep the command disabled or clearly safe in production if it can create noisy alerts.
- Declare the command in manifest metadata only if the package actually registers it.

Verification:

```bash
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
composer manifest:check
```

## Verification

Plan-pass verification performed:

- Reviewed package source, provider, Action, Mailable, sanitizer, Blade mail view, config, translations, manifest, README/docs, screenshot manifest/assets, package tests, root autoload entries, and split workflow references.
- Confirmed the broad review finding statically: `ReportExceptionByEmailAction` captures full URL and route parameters, while `ExceptionReportMailSanitizer` performs markup/control-character cleanup but no secret redaction.
- Confirmed existing tests preserve route parameters and do not cover signed URL/query secret redaction.
- Confirmed this worktree is a git repository and was clean before the plan file was added.

Commands used during inspection:

```bash
git status --short --branch
rg --files packages/exception-reports
rg -n "fullUrl|route_parameters|unsafe|sanitize|signature|EXCEPTION_REPORT_RECIPIENT|capell-app/exception-reports|ExceptionReports" packages/exception-reports
grep -RIn "exception-reports" docs .github packages/exception-reports
```

Plan-file verification run after this edit:

```bash
git diff --check
composer validate --no-check-publish
npx --yes prettier@3.8.4 --check --no-config packages/exception-reports/docs/improvement-plan.md
for heading in "## Snapshot" "## Improvements" "## Missing Features" "## Issues/Risks" "## Marketplace & Positioning" "## Prioritized Roadmap" "## Verification" "## Completion Checklist"; do grep -Fq "$heading" packages/exception-reports/docs/improvement-plan.md || exit 1; done
```

Runtime package tests were attempted but not completed in this worktree:

- `vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml` failed because `vendor/bin/pest` is not present.
- `./capell run vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml` failed because Docker Desktop is not sharing `/Users/ben/.codex/worktrees/67d9` with containers.

If `composer.local.json` is present in the active checkout, use the repository-standard overlay for Composer script checks:

```bash
COMPOSER=composer.local.json composer preflight
```

This worktree currently exposes only root `composer.json`, so use root Composer scripts directly or the Docker harness if the local overlay is unavailable.

## Completion Checklist

- [x] Inspect package source, provider, mail view, config, manifest, docs, assets, and tests.
- [x] Verify the known broad review finding about full URL and route parameter secret leakage.
- [x] Record the first implementation priority as secret redaction before queue/mail output.
- [ ] Add failing tests for signed URL query secrets and tokenized route parameters.
- [ ] Redact secrets before mailable serialization and render output.
- [ ] Add package-local typed report data boundaries.
- [ ] Add operational config and health-check coverage for disabled reporting, recipient validation, legacy fallback, and optional context capture.
- [ ] Align README, docs overview, manifest security metadata, and marketplace copy after the code fix lands.
- [ ] Re-run package Pest tests and focused static checks after implementation.
