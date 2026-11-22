<?php

declare(strict_types=1);

return [
    'health' => [
        'provider' => [
            'label' => 'Exception Reports: Meldedienst registriert',
            'ready' => 'Der Exception-Reports-Provider ist geladen.',
            'not_ready' => 'Der Exception-Reports-Provider ist nicht geladen.',
            'remediation' => 'Stellen Sie sicher, dass capell-app/exception-reports installiert ist und von Laravel erkannt wird.',
        ],
        'recipient' => [
            'label' => 'Exception Reports: Empfänger konfiguriert',
            'ready' => 'Für E-Mails zu Exception Reports ist ein Empfänger konfiguriert.',
            'legacy_ready' => 'E-Mails zu Exception Reports verwenden den veralteten Empfänger-Fallback services.exception_reports.to.',
            'not_ready' => 'Für E-Mails zu Exception Reports ist kein Empfänger konfiguriert.',
            'remediation' => 'Legen Sie EXCEPTION_REPORT_RECIPIENT oder capell-exception-reports.recipient fest.',
        ],
        'mailer' => [
            'label' => 'Exception Reports: E-Mail-Dienst konfiguriert',
            'ready' => 'Der konfigurierte Laravel-Mailer steht für E-Mails zu Exception Reports zur Verfügung.',
            'not_ready' => 'Der konfigurierte Laravel-Mailer fehlt oder kann nicht aufgelöst werden.',
            'remediation' => 'Legen Sie MAIL_MAILER auf einen konfigurierten Eintrag in mail.mailers fest, der von Laravel aufgelöst werden kann.',
        ],
        'from_address' => [
            'label' => 'Exception Reports: Absenderadresse konfiguriert',
            'ready' => 'E-Mails zu Exception Reports haben eine gültige Absenderadresse.',
            'not_ready' => 'E-Mails zu Exception Reports haben keine gültige Absenderadresse.',
            'remediation' => 'Legen Sie MAIL_FROM_ADDRESS auf eine gültige E-Mail-Adresse fest.',
        ],
        'queue' => [
            'label' => 'Exception Reports: Versandwarteschlange konfiguriert',
            'ready' => 'Die konfigurierte Laravel-Queue-Verbindung steht für E-Mails zu Exception Reports in der Warteschlange zur Verfügung.',
            'not_ready' => 'Die konfigurierte Laravel-Queue-Verbindung fehlt oder kann nicht aufgelöst werden.',
            'remediation' => 'Legen Sie QUEUE_CONNECTION auf einen konfigurierten Eintrag in queue.connections fest, der von Laravel aufgelöst werden kann.',
        ],
        'mail' => [
            'label' => 'Exception Reports: Report-E-Mail wird gerendert',
            'ready' => 'Die Mailable in der Warteschlange, der Sanitiser und die Markdown-Ansicht sind verfügbar.',
            'not_ready' => 'Die Mailable in der Warteschlange, der Sanitiser oder die Markdown-Ansicht ist nicht verfügbar.',
            'remediation' => 'Stellen Sie sicher, dass die Paket-Views und Übersetzungen von ExceptionReportsServiceProvider geladen werden.',
        ],
        'rate_limiter' => [
            'label' => 'Exception Reports: Rate Limiter verfügbar',
            'ready' => 'Der cachebasierte Rate Limiter und der Exception-Handler sind verfügbar.',
            'not_ready' => 'Der cachebasierte Rate Limiter oder der Exception-Handler ist nicht verfügbar.',
            'remediation' => 'Stellen Sie sicher, dass der Laravel-Cache-Dienst und der Exception-Handler aufgelöst werden können.',
        ],
        'webhook' => [
            'label' => 'Exception Reports: Webhook konfiguriert',
            'ready' => 'Der optionale Webhook für Exception Reports hat einen gültigen HTTP-Endpunkt.',
            'disabled' => 'Der optionale Webhook für Exception Reports ist deaktiviert.',
            'not_ready' => 'Der optionale Webhook für Exception Reports ist ohne gültigen HTTP-Endpunkt aktiviert.',
            'remediation' => 'Legen Sie CAPELL_EXCEPTION_REPORTS_WEBHOOK_URL auf einen http- oder https-Endpunkt fest oder deaktivieren Sie CAPELL_EXCEPTION_REPORTS_WEBHOOK_ENABLED.',
        ],
    ],
];
