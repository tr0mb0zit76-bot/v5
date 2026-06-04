<?php

$appUrlHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
$defaultMailHost = is_string($appUrlHost) && $appUrlHost !== ''
    ? 'mail.'.$appUrlHost
    : 'imap.hosting.reg.ru';

return [

    'enabled' => filter_var(env('MAIL_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL),

    'imap' => [
        'host' => env('MAIL_SYNC_IMAP_HOST', $defaultMailHost),
        'port' => (int) env('MAIL_SYNC_IMAP_PORT', 993),
        'encryption' => env('MAIL_SYNC_IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => filter_var(env('MAIL_SYNC_IMAP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    | Папки для чтения (первая существующая wins). reg.ru часто: INBOX + Sent или INBOX.Sent.
    */
    'folders' => [
        'inbound' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('MAIL_SYNC_IMAP_INBOX_FOLDERS', 'INBOX')),
        ))),
        'outbound' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('MAIL_SYNC_IMAP_SENT_FOLDERS', 'Sent')),
        ))),
    ],

    'initial_sync_days' => max(1, min(180, (int) env('MAIL_SYNC_INITIAL_DAYS', 30))),
    'max_messages_per_user' => max(10, min(1000, (int) env('MAIL_SYNC_MAX_MESSAGES', 200))),

];
