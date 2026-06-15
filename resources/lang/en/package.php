<?php

declare(strict_types=1);

return [
    'health' => [
        'provider' => [
            'label' => 'Exception Reports provider',
            'ready' => 'The Exception Reports provider is loaded.',
            'not_ready' => 'The Exception Reports provider is not loaded.',
            'remediation' => 'Ensure capell-app/exception-reports is installed and discovered by Laravel.',
        ],
        'recipient' => [
            'label' => 'Exception report recipient',
            'ready' => 'Exception report emails have a configured recipient.',
            'legacy_ready' => 'Exception report emails are using the legacy services.exception_reports.to recipient fallback.',
            'not_ready' => 'Exception report emails do not have a configured recipient.',
            'remediation' => 'Set EXCEPTION_REPORT_RECIPIENT or capell-exception-reports.recipient.',
        ],
        'mailer' => [
            'label' => 'Exception report mailer',
            'ready' => 'The configured Laravel mailer is available for exception report emails.',
            'not_ready' => 'The configured Laravel mailer is missing or cannot be resolved.',
            'remediation' => 'Set MAIL_MAILER to a configured mail.mailers entry that can be resolved by Laravel.',
        ],
        'from_address' => [
            'label' => 'Exception report from address',
            'ready' => 'Exception report emails have a valid from address.',
            'not_ready' => 'Exception report emails do not have a valid from address.',
            'remediation' => 'Set MAIL_FROM_ADDRESS to a valid email address.',
        ],
        'queue' => [
            'label' => 'Exception report queue connection',
            'ready' => 'The configured Laravel queue connection is available for queued exception report emails.',
            'not_ready' => 'The configured Laravel queue connection is missing or cannot be resolved.',
            'remediation' => 'Set QUEUE_CONNECTION to a configured queue.connections entry that can be resolved by Laravel.',
        ],
        'mail' => [
            'label' => 'Exception report mail renderer',
            'ready' => 'The queued mailable, sanitizer, and Markdown view are available.',
            'not_ready' => 'The queued mailable, sanitizer, or Markdown view is unavailable.',
            'remediation' => 'Ensure package views and translations are loaded by ExceptionReportsServiceProvider.',
        ],
        'rate_limiter' => [
            'label' => 'Exception report rate limiter',
            'ready' => 'The cache-backed rate limiter and exception handler are available.',
            'not_ready' => 'The cache-backed rate limiter or exception handler is unavailable.',
            'remediation' => 'Ensure the Laravel cache service and exception handler can be resolved.',
        ],
    ],
];
