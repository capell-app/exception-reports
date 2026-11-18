# Changelog

## Unreleased

- Emailed, digest and webhook reports and the package's log lines now carry the same scrubbed message as the stored record: a database error keeps only its SQLSTATE code, never the statement or bound values. Messages are sanitised before they are shortened, and an included trace no longer shows call arguments.
- The package now schedules `capell:exception-reports-prune` daily, so stored reports are deleted after the retention window as documented. It deletes in chunks of 1000.
- Redacted request URL paths and query values from exception records and notifications; matched route templates remain available for diagnosis. Existing records are unchanged.
