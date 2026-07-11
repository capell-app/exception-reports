<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Mail;

use Capell\ExceptionReports\Data\ExceptionReportData;
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

    public readonly ExceptionReportData $report;

    public function __construct(ExceptionReportData $report)
    {
        $this->report = ExceptionReportData::from(
            resolve(ExceptionReportMailSanitizer::class)->sanitize($report->toArray()),
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->report->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'capell-exception-reports::mail.reported',
            with: [
                'safeReport' => $this->report->toArray(),
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
