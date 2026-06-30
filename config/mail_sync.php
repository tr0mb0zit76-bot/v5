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
        'open_timeout_seconds' => max(5, min(120, (int) env('MAIL_SYNC_IMAP_OPEN_TIMEOUT_SECONDS', 30))),
        'read_timeout_seconds' => max(5, min(300, (int) env('MAIL_SYNC_IMAP_READ_TIMEOUT_SECONDS', 60))),
        'write_timeout_seconds' => max(5, min(120, (int) env('MAIL_SYNC_IMAP_WRITE_TIMEOUT_SECONDS', 30))),
        'close_timeout_seconds' => max(5, min(120, (int) env('MAIL_SYNC_IMAP_CLOSE_TIMEOUT_SECONDS', 10))),
    ],

    'command_time_limit_seconds' => max(60, min(3600, (int) env('MAIL_SYNC_COMMAND_TIME_LIMIT_SECONDS', 900))),

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

    /*
    | При плановом sync (без --days) окно начинается с mail_last_sync_at минус overlap.
    | Первый прогон для ящика — initial_sync_days.
    */
    'incremental_overlap_hours' => max(0, min(168, (int) env('MAIL_SYNC_INCREMENTAL_OVERLAP_HOURS', 24))),

    /*
    | Синхронизировать только ящики на этих доменах (reg.ru / корпоративная почта).
    | Пользователи с @mail.ru, @log-sol.ru и т.п. в очередь sync не попадают.
    */
    'mailbox_domains' => array_values(array_filter(array_map(
        static fn (string $value): string => strtolower(ltrim(trim($value), '@')),
        explode(',', (string) env('MAIL_SYNC_MAILBOX_DOMAINS', 'avtoaliyans.ru')),
    ))),

    /*
    | Устаревший режим: импорт только если участник в allowlist контрагентов.
    | По умолчанию false — импортируем все входящие, кроме отправителей из mail_blocked_senders.
    */
    'require_contractor_match' => filter_var(env('MAIL_SYNC_REQUIRE_CONTRACTOR_MATCH', false), FILTER_VALIDATE_BOOL),

    'allowlist_cache_seconds' => max(60, min(3600, (int) env('MAIL_SYNC_ALLOWLIST_CACHE_SECONDS', 300))),

    'spam_blocklist_cache_seconds' => max(60, min(3600, (int) env('MAIL_SYNC_SPAM_BLOCKLIST_CACHE_SECONDS', 300))),

    /*
    | Для этих доменов в allowlist попадает только полный адрес, не весь домен.
    */
    'import_html_body' => filter_var(env('MAIL_SYNC_IMPORT_HTML', true), FILTER_VALIDATE_BOOL),

    'max_html_body_chars' => max(10_000, min(500_000, (int) env('MAIL_SYNC_MAX_HTML_CHARS', 200_000))),

    'inbound_attachments' => [
        'enabled' => filter_var(env('MAIL_SYNC_IMPORT_ATTACHMENTS', true), FILTER_VALIDATE_BOOL),
        'max_files_per_message' => max(1, min(20, (int) env('MAIL_SYNC_INBOUND_MAX_ATTACHMENTS', 10))),
        'max_file_kb' => max(256, min(51200, (int) env('MAIL_SYNC_INBOUND_MAX_ATTACHMENT_KB', 15360))),
    ],

    'outbound_attachments' => [
        'max_files' => max(1, min(10, (int) env('MAIL_OUTBOUND_MAX_ATTACHMENTS', 5))),
        'max_file_kb' => max(256, min(51200, (int) env('MAIL_OUTBOUND_MAX_ATTACHMENT_KB', 10240))),
        'allowed_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'txt', 'csv'],
    ],

    'public_mail_domains' => [
        'gmail.com',
        'googlemail.com',
        'yandex.ru',
        'ya.ru',
        'yandex.com',
        'mail.ru',
        'inbox.ru',
        'list.ru',
        'bk.ru',
        'internet.ru',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'rambler.ru',
        'lenta.ru',
        'autorambler.ru',
        'ro.ru',
        'yahoo.com',
        'proton.me',
        'protonmail.com',
    ],

];
