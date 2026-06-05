<?php

namespace App\Mail;

use App\Services\DocumentStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CommercialOutboundMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $fromEmail,
        public string $fromName,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public ?string $references = null,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
        public ?string $attachmentDriver = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: $this->subjectLine,
            using: [
                function (Email $email): void {
                    if ($this->messageId !== null && $this->messageId !== '') {
                        $id = trim($this->messageId, '<>');
                        $email->getHeaders()->addIdHeader('Message-ID', $id);
                    }

                    if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
                        $email->getHeaders()->addTextHeader('In-Reply-To', $this->inReplyTo);
                    }

                    if ($this->references !== null && $this->references !== '') {
                        $email->getHeaders()->addTextHeader('References', $this->references);
                    }
                },
            ],
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
