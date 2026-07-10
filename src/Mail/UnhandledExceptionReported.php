<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Mail;

use Capell\ExceptionReports\Support\ExceptionReportMailSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class UnhandledExceptionReported extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** @var array<string, mixed> */
    public readonly array $report;

    /** @param array<string, mixed> $report */
    public function __construct(array $report)
    {
        $this->report = resolve(ExceptionReportMailSanitizer::class)->sanitize($report);
    }

    public function envelope(): Envelope
    {
        $subject = $this->report['subject'] ?? __('capell-exception-reports::mail.default_subject');

        return new Envelope(
            subject: is_scalar($subject) ? (string) $subject : (string) __('capell-exception-reports::mail.default_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'capell-exception-reports::mail.reported',
            with: [
                'safeReport' => $this->report,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
