# Exception Reports Docs

Package-local documentation for `capell-app/exception-reports`.

| Document                                | Use                                                                 |
| --------------------------------------- | ------------------------------------------------------------------- |
| [Overview](overview.md)                 | Owner value, runtime behavior, setup, safety, and screenshot notes. |
| [Admin guide](admin-guide.md)           | Operator workflows for reviewing reports and configuring notifications. |
| [Screenshot manifest](screenshots.json) | Marketplace and docs screenshot capture contract.                   |

## Verification

```bash
vendor/bin/pest packages/exception-reports/tests --configuration=phpunit.xml
```
