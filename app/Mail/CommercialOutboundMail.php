<?php

namespace App\Mail;

use App\Services\DocumentStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommercialOutboundMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
        public ?string $attachmentDriver = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.commercial-outbound',
            with: [
                'bodyText' => $this->bodyText,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (blank($this->attachmentPath)) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => app(DocumentStorageService::class)
                    ->get((string) $this->attachmentPath, $this->attachmentDriver),
                (string) ($this->attachmentName ?: 'attachment.docx'),
            )->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
