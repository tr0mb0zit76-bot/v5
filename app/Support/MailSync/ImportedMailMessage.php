<?php

namespace App\Support\MailSync;

use DateTimeInterface;

final readonly class ImportedMailMessage
{
    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{filename: string, content: string, mime_type: string|null, size: int}>  $rawAttachments
     */
    public function __construct(
        public string $internetMessageId,
        public string $direction,
        public string $fromEmail,
        public array $toEmails,
        public array $ccEmails,
        public string $subject,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public ?string $inReplyTo,
        public ?DateTimeInterface $sentAt,
        public string $folder,
        public array $rawAttachments = [],
    ) {}
}
