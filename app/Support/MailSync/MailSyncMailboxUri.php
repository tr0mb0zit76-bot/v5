<?php

namespace App\Support\MailSync;

final class MailSyncMailboxUri
{
    public static function prefix(): string
    {
        return sprintf(
            '{%s:%d/imap/%s%s}',
            config('mail_sync.imap.host'),
            (int) config('mail_sync.imap.port', 993),
            config('mail_sync.imap.encryption', 'ssl'),
            config('mail_sync.imap.validate_cert', true) ? '' : '/novalidate-cert',
        );
    }

    public static function folder(string $folder): string
    {
        return self::prefix().$folder;
    }
}
