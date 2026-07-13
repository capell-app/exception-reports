<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'recipient' => env('EXCEPTION_REPORT_RECIPIENT', env('MAIL_FROM_ADDRESS')),

    'privacy' => [
        'include_ip_address' => filter_var(env('CAPELL_EXCEPTION_REPORTS_INCLUDE_IP_ADDRESS', false), FILTER_VALIDATE_BOOLEAN),
        'include_user_identity' => filter_var(env('CAPELL_EXCEPTION_REPORTS_INCLUDE_USER_IDENTITY', false), FILTER_VALIDATE_BOOLEAN),
        'include_trace' => filter_var(env('CAPELL_EXCEPTION_REPORTS_INCLUDE_TRACE', false), FILTER_VALIDATE_BOOLEAN),
        'route_parameter_allowlist' => array_filter(explode(',', (string) env('CAPELL_EXCEPTION_REPORTS_ROUTE_PARAMETER_ALLOWLIST', ''))),
    ],

    'rate_limits' => [
        'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_RATE_LIMITS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
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
        'allowed_hosts' => array_filter(explode(',', (string) env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_ALLOWED_HOSTS', ''))),
        'signing_secret' => env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_SIGNING_SECRET'),
        'timeout_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_TIMEOUT_SECONDS', 5),
        'connect_timeout_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_CONNECT_TIMEOUT_SECONDS', 2),
        'include_trace' => filter_var(env('CAPELL_EXCEPTION_REPORTS_WEBHOOK_INCLUDE_TRACE', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
