# Investigating persisted exception reports

For developers with authorised access to the affected host application.

### How to investigate without an email

1. Confirm the package migrations have run and `capell-exception-reports.persistence.enabled` is enabled (default `true`, controlled by `CAPELL_EXCEPTION_REPORTS_PERSISTENCE_ENABLED`). The default table is `exception_reports`; `CAPELL_EXCEPTION_REPORTS_PERSISTENCE_TABLE` can override it.
2. Use authorised database access or run `php artisan tinker` in the affected host application. Limit the time window and result count, for example:

```php
use Capell\ExceptionReports\Models\ExceptionReport;

ExceptionReport::query()
    ->where('reported_at', '>=', now()->subHour())
    ->where('exception_class', RuntimeException::class)
    ->orderByDesc('id')
    ->limit(20)
    ->get(['id', 'reported_at', 'exception_class', 'message', 'job_class', 'job_id', 'url']);
```

3. Correlate the sanitised message and exception class with the occurrence time. Job class/ID and request URL are nullable; the stored URL omits its query string. The message is sanitised and length-limited, not a full trace or copy of the email.
4. Investigate delivery separately using the host queue, mail provider, webhook and application logs. A row does not prove that mail was queued or delivered. Rate-limited occurrences still produce rows; missing mail configuration does not prevent persistence. Excluded exceptions and persistence failures can leave no row, so an empty query does not prove no exception occurred.
5. The host operator owns database access, backups and retention. These rows can contain sensitive operational context even after sanitisation. Restrict access and define a retention/deletion process; this package supplies no automatic pruning, admin inbox or durable delivery-replay queue. Turning persistence off stops future writes and does not delete existing rows.
