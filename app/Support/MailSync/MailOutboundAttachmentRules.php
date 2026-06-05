<?php

namespace App\Support\MailSync;

final class MailOutboundAttachmentRules
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $field = 'attachments'): array
    {
        $maxFiles = max(1, (int) config('mail_sync.outbound_attachments.max_files', 5));
        $maxFileKb = max(64, (int) config('mail_sync.outbound_attachments.max_file_kb', 10240));
        $mimes = implode(',', self::allowedExtensions());

        return [
            $field => ['nullable', 'array', 'max:'.$maxFiles],
            $field.'.*' => ['file', 'max:'.$maxFileKb, 'mimes:'.$mimes],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        $configured = config('mail_sync.outbound_attachments.allowed_mimes');

        if (! is_array($configured) || $configured === []) {
            return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'txt', 'csv'];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $configured,
        )));
    }

    public static function hintRu(): string
    {
        $maxFiles = max(1, (int) config('mail_sync.outbound_attachments.max_files', 5));
        $maxMb = max(1, (int) config('mail_sync.outbound_attachments.max_file_kb', 10240)) / 1024;

        return sprintf(
            'До %d файлов, каждый до %.0f МиБ (%s).',
            $maxFiles,
            $maxMb,
            implode(', ', self::allowedExtensions()),
        );
    }
}
