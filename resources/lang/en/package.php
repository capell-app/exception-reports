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
            'not_ready' => 'Exception report emails do not have a configured recipient.',
            'remediation' => 'Set EXCEPTION_REPORT_RECIPIENT or capell-exception-reports.recipient.',
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
