<?php

namespace App\Services\Commercial;

use App\Models\User;
use App\Services\DocumentStorageService;

final class MailInboundAttachmentStorage
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @param  list<array{filename: string, content: string, mime_type: string|null, size: int}>  $rawAttachments
     * @return list<array{original_name: string, file_path: string, storage_driver: string, mime_type: string|null, file_size: int}>
     */
    public function storeForMessage(User $mailboxUser, int $messageId, array $rawAttachments): array
    {
        if (! config('mail_sync.inbound_attachments.enabled', true) || $rawAttachments === []) {
            return [];
        }

        $maxFiles = max(1, (int) config('mail_sync.inbound_attachments.max_files_per_message', 10));
        $maxBytes = max(1024, (int) config('mail_sync.inbound_attachments.max_file_kb', 15360)) * 1024;
        $stored = [];

        foreach (array_slice($rawAttachments, 0, $maxFiles) as $attachment) {
            $content = (string) ($attachment['content'] ?? '');
            $size = (int) ($attachment['size'] ?? strlen($content));

            if ($content === '' || $size <= 0 || $size > $maxBytes) {
                continue;
            }

            $meta = $this->documentStorage->storeMailInboundAttachment(
                $content,
                (string) ($attachment['filename'] ?? 'attachment'),
                $mailboxUser->id,
                $messageId,
            );

            $stored[] = [
                'original_name' => $meta['original_name'],
                'file_path' => $meta['file_path'],
                'storage_driver' => $meta['storage_driver'],
                'mime_type' => $attachment['mime_type'] ?? $meta['mime_type'],
                'file_size' => $meta['file_size'],
            ];
        }

        return $stored;
    }
}
