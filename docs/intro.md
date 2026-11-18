The client rings to say the checkout page is broken, and it's the first you've heard of it. With this, a crash on your site emails you, usually before the phone rings. It reacts to unhandled exceptions, errors the site didn't deal with, that Laravel (the framework behind Capell) reports.

The email goes to your site's sending address, and picking another needs a developer because the admin has no setting for it. There's no page of errors or way to mark one resolved. Each exception is also saved as a small database record, hidden from the admin and deleted after 30 days by default, if your server's scheduler is running. A retention of 0 in server settings keeps them forever. If a developer sets one up, the same report can go to a webhook, an approved web address for your own tools.

By default, identical errors send one email every 15 minutes, and at most ten emails go out an hour. Anything held back is saved but not emailed.

Each email names the error, the file and line, and the environment. It names the page or command by its internal name, not its web address. The subject line carries your app name, the error type and that page or command, so you know where the trouble is before opening it.
