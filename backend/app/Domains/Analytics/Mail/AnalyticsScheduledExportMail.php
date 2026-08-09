<?php

namespace App\Domains\Analytics\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnalyticsScheduledExportMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $salonName,
        public readonly string $reportName,
        public readonly string $exportJobId,
        public readonly string $fileDisk,
        public readonly string $filePath,
        public readonly string $fileName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Scheduled report: '.$this->reportName.' — '.$this->salonName,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Your scheduled analytics export <strong>'.e($this->reportName)
                .'</strong> from '.e($this->salonName).' is attached.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->fileDisk, $this->filePath)
                ->as($this->fileName),
        ];
    }
}
