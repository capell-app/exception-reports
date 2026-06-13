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

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public readonly array $report) {}

    public function envelope(): Envelope
    {
        $safeReport = $this->safeReport();
        $subject = $safeReport['subject'] ?? __('capell-exception-reports::mail.default_subject');

        return new Envelope(
            subject: is_scalar($subject) ? (string) $subject : (string) __('capell-exception-reports::mail.default_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'capell-exception-reports::mail.reported',
            with: [
                'safeReport' => $this->safeReport(),
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

    /**
     * @return array<string, mixed>
     */
    private function safeReport(): array
    {
        return resolve(ExceptionReportMailSanitizer::class)->sanitize($this->report);
    }
}
