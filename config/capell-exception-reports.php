<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'recipient' => env('EXCEPTION_REPORT_RECIPIENT', env('MAIL_FROM_ADDRESS')),

    // Trace frames can contain request values and filesystem paths. Keep them opt-in.
    'include_trace' => filter_var(env('CAPELL_EXCEPTION_REPORTS_INCLUDE_TRACE', false), FILTER_VALIDATE_BOOLEAN),

    'rate_limits' => [
        'signature_attempts' => (int) env('CAPELL_EXCEPTION_REPORTS_SIGNATURE_ATTEMPTS', 1),
        'signature_decay_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_SIGNATURE_DECAY_SECONDS', 60 * 15),
        'global_attempts' => (int) env('CAPELL_EXCEPTION_REPORTS_GLOBAL_ATTEMPTS', 10),
        'global_decay_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_GLOBAL_DECAY_SECONDS', 60 * 60),
    ],

    'digest' => [
        'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_DIGEST_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'threshold' => (int) env('CAPELL_EXCEPTION_REPORTS_DIGEST_THRESHOLD', 5),
        'window_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_DIGEST_WINDOW_SECONDS', 60 * 60),
    ],

    'webhook' => [
        'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL'),
        'timeout_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_TIMEOUT_SECONDS', 5),
        'include_trace' => filter_var(env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_INCLUDE_TRACE', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
