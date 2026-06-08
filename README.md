# Exception Reports

Exception Reports emails operators when a Capell app reports an unhandled exception. It includes sanitized app, request, route, user, and stack-trace context and uses signature/global rate limits to avoid email floods.

Configure the recipient with `EXCEPTION_REPORT_RECIPIENT` or `capell-exception-reports.recipient`.
