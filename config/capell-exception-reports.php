<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('CAPELL_EXCEPTION_REPORTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'recipient' => env('EXCEPTION_REPORT_RECIPIENT', env('MAIL_FROM_ADDRESS')),

    'rate_limits' => [
        'signature_attempts' => (int) env('CAPELL_EXCEPTION_REPORTS_SIGNATURE_ATTEMPTS', 1),
        'signature_decay_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_SIGNATURE_DECAY_SECONDS', 60 * 15),
        'global_attempts' => (int) env('CAPELL_EXCEPTION_REPORTS_GLOBAL_ATTEMPTS', 10),
        'global_decay_seconds' => (int) env('CAPELL_EXCEPTION_REPORTS_GLOBAL_DECAY_SECONDS', 60 * 60),
    ],
];
