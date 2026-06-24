# Using Exception Reports

This guide is for owners and operators who decide how technical errors are routed and who needs to hear about them. Exception Reports quietly records the errors your site runs into and sends a report where you choose, so problems do not go unnoticed. Most of the time there is nothing to do; your job is to make sure the right person is told and to pass the detail to a developer. No technical knowledge needed. Every step uses the labels you see on screen.

## Using Exception Reports (how-to)

### How to see when errors were reported

1. Open **Exception Reports** in the admin.
2. Each entry is one error the site ran into, with **Reported at** showing when it happened.
3. Scan the list to see whether errors are rare one-offs or a recurring pattern.

### How to read an error report

1. Open the report from email or from the admin.
2. The report shows the context a developer needs: the **Message**, the **Location**, the **Environment**, the **Request** details (such as the **URL**, **Method**, and **Route**), and a **Stack Trace**.
3. Unsafe diagnostic content is stripped out before the report is sent, so you can pass it on without exposing sensitive markup. If anything was removed you will see a note that unsafe content was stripped.
4. You do not need to understand every field. The detail is for whoever fixes the error.

### How to choose who is notified

1. Open the notification settings for Exception Reports.
2. Set the **recipient** so reports reach the right person or inbox.
3. If your team uses a webhook (for example to post into a chat tool), set that destination as well.
4. Save. New reports now go to that destination automatically.

### How to share a report with your developer

1. Open the report.
2. Forward the report email, or copy the recorded detail, to your developer.
3. The content is technical and is meant for them. Your role is to make sure it reaches someone who can act on it, not to fix it yourself.

### How to handle a flood of notifications

1. If the same error keeps firing, repeated reports are grouped so you are not flooded. A grouped report shows a **Digest** summarising how many matching reports happened in the window.
2. If you are still getting too many notifications, ask your developer to adjust the rate limit or the digest threshold.

## Rolling out Exception Reports (for owners)

### Turn on first

- **A recipient for reports.** Set who is notified before you rely on the package, so the first real error reaches a person instead of nowhere.

### Add when needed

| Need                                     | What to use                                              |
| ---------------------------------------- | -------------------------------------------------------- |
| Make sure errors reach a person          | Set the **recipient** in the notification settings       |
| Post errors into a chat or other tool    | A webhook destination (ask your developer to wire it up) |
| Avoid being flooded by a repeating error | Grouped reports and the **Digest** summary               |
| Pass full technical detail on            | Forward the report to a developer                        |

### Who does what

| Role       | What they do                                                              |
| ---------- | ------------------------------------------------------------------------- |
| Site owner | Set the **recipient**, watch for recurring errors, forward to a developer |
| Developer  | Reads the **Stack Trace** and request detail, and fixes the cause         |

## Troubleshooting

| What you see                           | What it means                                       | What to do                                                      |
| -------------------------------------- | --------------------------------------------------- | --------------------------------------------------------------- |
| A new error is reported                | The site hit a problem it could not handle          | Forward the report to your developer; the detail is for them    |
| The same error keeps recurring         | One underlying issue is firing repeatedly           | Flag the pattern to your developer as a priority                |
| No one seems to be getting the reports | No recipient is set, or the wrong one is            | Set the **recipient** in the notification settings              |
| You get too many notifications         | A noisy error is firing often                       | Ask your developer to adjust the rate limit or digest threshold |
| A report notes content was stripped    | Unsafe diagnostic markup was removed before sending | This is expected and safe; pass the report on as normal         |
