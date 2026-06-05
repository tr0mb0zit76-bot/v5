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

    /**
     * @param  list<array{path: string, name: string, driver: string|null, mime_type: string|null}>  $outboundAttachments
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $fromEmail,
        public string $fromName,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public ?string $references = null,
        public array $outboundAttachments = [],
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
        if ($this->outboundAttachments === []) {
            return [];
        }

        $storage = app(DocumentStorageService::class);

        return array_values(array_map(
            static function (array $attachment) use ($storage): Attachment {
                $mime = trim((string) ($attachment['mime_type'] ?? ''));

                $mailAttachment = Attachment::fromData(
                    fn (): string => $storage->get(
                        (string) $attachment['path'],
                        $attachment['driver'] ?? null,
                    ),
                    (string) ($attachment['name'] ?: 'attachment'),
                );

                if ($mime !== '') {
                    $mailAttachment = $mailAttachment->withMime($mime);
                }

                return $mailAttachment;
            },
            $this->outboundAttachments,
        ));
    }
}
