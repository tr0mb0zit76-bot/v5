<?php

namespace App\Support\MailSync;

use Carbon\CarbonImmutable;
use IMAP\Connection;
use RuntimeException;

final class MailImapClient
{
    /** @var Connection|resource|null */
    private $connection = null;

    private ?string $connectedFolder = null;

    public function __construct(
        private readonly MailMimeBodyExtractor $bodyExtractor = new MailMimeBodyExtractor,
        private readonly MailMimeAttachmentExtractor $attachmentExtractor = new MailMimeAttachmentExtractor,
    ) {}

    public function extensionLoaded(): bool
    {
        return function_exists('imap_open');
    }

    /**
     * @param  array{uids?: int, parsed?: int, search?: string}|null  $diagnostics
     * @return list<ImportedMailMessage>
     */
    public function fetchSince(
        string $username,
        string $password,
        string $folder,
        string $direction,
        CarbonImmutable $since,
        int $limit,
        ?array &$diagnostics = null,
    ): array {
        if (! $this->extensionLoaded()) {
            throw new RuntimeException('PHP extension imap не установлена. Установите ext-imap на сервере.');
        }

        $this->openFolder($username, $password, $folder);

        [$uids, $searchMode] = $this->searchUidsSince($since, $limit);

        $messages = [];
        $parsed = 0;

        foreach ($uids as $uid) {
            $imported = $this->parseMessage((int) $uid, $direction, $folder);

            if ($imported !== null) {
                $messages[] = $imported;
                $parsed++;
            }
        }

        if ($diagnostics !== null) {
            $diagnostics = [
                'uids' => count($uids),
                'parsed' => $parsed,
                'search' => $searchMode,
                'since' => $since->format('d-M-Y'),
                'mailbox' => MailSyncMailboxUri::prefix(),
                'imap_error' => trim((string) imap_last_error()) ?: null,
            ];
        }

        return $messages;
    }

    /**
     * @return array{0: list<int>, 1: string}
     */
    private function searchUidsSince(CarbonImmutable $since, int $limit): array
    {
        if (! MailImapConnection::isActive($this->connection)) {
            return [[], 'none'];
        }

        $searchDate = $since->format('d-M-Y');
        imap_errors();
        imap_alerts();

        /** @var list<int>|false $uids */
        $uids = imap_search($this->connection, 'SINCE "'.$searchDate.'"', SE_UID);

        if (is_array($uids) && $uids !== []) {
            rsort($uids);

            return [array_slice($uids, 0, $limit), 'SINCE'];
        }

        /** @var list<int>|false $all */
        $all = imap_search($this->connection, 'ALL', SE_UID);

        if (is_array($all) && $all !== []) {
            rsort($all);
            $sinceTs = $since->getTimestamp();
            $matched = [];

            foreach ($all as $uid) {
                if (! $this->uidIsSince((int) $uid, $sinceTs)) {
                    continue;
                }

                $matched[] = (int) $uid;

                if (count($matched) >= $limit) {
                    break;
                }
            }

            if ($matched !== []) {
                return [$matched, 'ALL(filtered)'];
            }
        }

        return [$this->scanUidsSince($since, $limit), 'num_msg(scan)'];
    }

    /**
     * @return list<int>
     */
    private function scanUidsSince(CarbonImmutable $since, int $limit): array
    {
        if (! MailImapConnection::isActive($this->connection)) {
            return [];
        }

        $total = imap_num_msg($this->connection);

        if (! is_int($total) || $total <= 0) {
            return [];
        }

        $sinceTs = $since->getTimestamp();
        $matched = [];

        for ($msgno = $total; $msgno >= 1; $msgno--) {
            $uid = imap_uid($this->connection, $msgno);

            if (! is_int($uid) || $uid <= 0) {
                continue;
            }

            if (! $this->uidIsSince($uid, $sinceTs)) {
                continue;
            }

            $matched[] = $uid;

            if (count($matched) >= $limit) {
                break;
            }
        }

        return $matched;
    }

    private function uidIsSince(int $uid, int $sinceTimestamp): bool
    {
        if (! MailImapConnection::isActive($this->connection)) {
            return false;
        }

        $overview = imap_fetch_overview($this->connection, (string) $uid, FT_UID);
        $meta = $overview[0] ?? null;

        if ($meta === null || ! isset($meta->date) || ! is_string($meta->date)) {
            return true;
        }

        try {
            return CarbonImmutable::parse($meta->date)->getTimestamp() >= $sinceTimestamp;
        } catch (\Throwable) {
            return true;
        }
    }

    public function disconnect(): void
    {
        if (MailImapConnection::isActive($this->connection)) {
            imap_close($this->connection);
        }

        $this->connection = null;
        $this->connectedFolder = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function openFolder(string $username, string $password, string $folder): void
    {
        if (MailImapConnection::isActive($this->connection) && $this->connectedFolder === $folder) {
            return;
        }

        $this->disconnect();
        $this->configureTimeouts();

        $mailbox = MailSyncMailboxUri::folder($folder);

        $connection = imap_open($mailbox, $username, $password, OP_READONLY, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if ($connection === false) {
            throw new RuntimeException(trim((string) imap_last_error()) ?: 'Не удалось подключиться к IMAP.');
        }

        $this->connection = $connection;
        $this->connectedFolder = $folder;
    }

    private function configureTimeouts(): void
    {
        if (! function_exists('imap_timeout')) {
            return;
        }

        imap_timeout(IMAP_OPENTIMEOUT, (int) config('mail_sync.imap.open_timeout_seconds', 30));
        imap_timeout(IMAP_READTIMEOUT, (int) config('mail_sync.imap.read_timeout_seconds', 60));
        imap_timeout(IMAP_WRITETIMEOUT, (int) config('mail_sync.imap.write_timeout_seconds', 30));
        imap_timeout(IMAP_CLOSETIMEOUT, (int) config('mail_sync.imap.close_timeout_seconds', 10));
    }

    private function parseMessage(int $uid, string $direction, string $folder): ?ImportedMailMessage
    {
        if (! MailImapConnection::isActive($this->connection)) {
            return null;
        }

        $header = imap_fetchheader($this->connection, $uid, FT_UID);

        if ($header === false) {
            return null;
        }

        $internetMessageId = $this->resolveMessageId($header, $uid, $folder);

        $overview = imap_fetch_overview($this->connection, (string) $uid, FT_UID);
        $meta = $overview[0] ?? null;

        $fromEmail = $this->normalizeEmail($meta->from ?? '') ?? $this->headerValue($header, 'From') ?? '';
        $toRaw = $meta->to ?? $this->headerValue($header, 'To') ?? '';
        $ccRaw = $meta->cc ?? $this->headerValue($header, 'Cc') ?? '';
        $rawSubject = isset($meta->subject) ? (string) $meta->subject : ($this->headerValue($header, 'Subject') ?? '');
        $subject = $this->decodeMimeHeader($rawSubject);
        $sentAt = null;

        if (isset($meta->date) && is_string($meta->date)) {
            try {
                $sentAt = CarbonImmutable::parse($meta->date);
            } catch (\Throwable) {
                $sentAt = null;
            }
        }

        $bodyText = $this->bodyExtractor->extractPlainText($this->connection, $uid);
        $bodyHtml = config('mail_sync.import_html_body', true)
            ? $this->bodyExtractor->extractHtml($this->connection, $uid)
            : null;
        $rawAttachments = config('mail_sync.inbound_attachments.enabled', true)
            ? $this->attachmentExtractor->extract($this->connection, $uid)
            : [];

        if ($bodyText === '' && $bodyHtml !== null && $bodyHtml !== '') {
            $bodyText = MailHtmlSanitizer::toPlainText($bodyHtml);
        }

        $maxHtmlChars = max(10_000, (int) config('mail_sync.max_html_body_chars', 200_000));

        if ($bodyHtml !== null && mb_strlen($bodyHtml) > $maxHtmlChars) {
            $bodyHtml = mb_substr($bodyHtml, 0, $maxHtmlChars);
        }

        return new ImportedMailMessage(
            internetMessageId: $this->normalizeMessageId($internetMessageId),
            direction: $direction,
            fromEmail: $this->extractEmailAddress($fromEmail) ?? strtolower(trim($fromEmail)),
            toEmails: $this->extractEmailAddresses($toRaw),
            ccEmails: $this->extractEmailAddresses($ccRaw),
            subject: MailUtf8Sanitizer::sanitize(trim($subject)),
            bodyText: $bodyText !== '' ? MailHtmlSanitizer::cleanPlainText(MailUtf8Sanitizer::sanitize($bodyText)) : null,
            bodyHtml: $bodyHtml,
            inReplyTo: $this->normalizeMessageId($this->headerValue($header, 'In-Reply-To') ?? ''),
            sentAt: $sentAt,
            folder: $folder,
            rawAttachments: $rawAttachments,
        );
    }

    private function resolveMessageId(string $header, int $uid, string $folder): string
    {
        $parsed = imap_rfc822_parse_headers($header);

        if ($parsed !== false && isset($parsed->message_id) && is_string($parsed->message_id)) {
            $fromParser = $this->normalizeMessageId($parsed->message_id);

            if ($fromParser !== '') {
                return $fromParser;
            }
        }

        $fromHeader = $this->headerValue($header, 'Message-ID')
            ?? $this->headerValue($header, 'Message-Id');

        if (is_string($fromHeader) && $fromHeader !== '') {
            return $this->normalizeMessageId($fromHeader);
        }

        return 'generated-'.hash('sha256', $folder.'|'.$uid.'|'.md5($header));
    }

    private function headerValue(string $header, string $name): ?string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/im', $header, $matches) !== 1) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1]);

        return $value !== '' ? $value : null;
    }

    private function normalizeMessageId(string $value): string
    {
        $value = trim($value);

        return trim($value, "<> \t\r\n");
    }

    private function decodeMimeHeader(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! function_exists('imap_mime_header_decode')) {
            return MailUtf8Sanitizer::sanitize($value);
        }

        $decoded = imap_mime_header_decode($value);

        if (! is_array($decoded)) {
            return MailUtf8Sanitizer::sanitize($value);
        }

        $parts = [];

        foreach ($decoded as $part) {
            $text = (string) ($part->text ?? '');
            $charset = strtolower(trim((string) ($part->charset ?? 'default')));

            if ($charset === 'default' || $charset === 'ascii' || $charset === 'us-ascii') {
                $parts[] = MailUtf8Sanitizer::sanitize($text);

                continue;
            }

            if ($charset === 'utf-8' || $charset === 'utf8') {
                $parts[] = MailUtf8Sanitizer::sanitize($text);

                continue;
            }

            $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
            $parts[] = MailUtf8Sanitizer::sanitize(is_string($converted) ? $converted : $text);
        }

        $joined = trim(implode('', $parts));

        return $joined !== '' ? $joined : MailUtf8Sanitizer::sanitize($value);
    }

    private function normalizeEmail(string $value): ?string
    {
        return $this->extractEmailAddress($value);
    }

    /**
     * @return list<string>
     */
    private function extractEmailAddresses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $raw, $matches);

        $emails = array_map(static fn (string $email): string => strtolower(trim($email)), $matches[0] ?? []);

        return array_values(array_unique(array_filter($emails)));
    }

    private function extractEmailAddress(string $raw): ?string
    {
        $emails = $this->extractEmailAddresses($raw);

        return $emails[0] ?? null;
    }
}
