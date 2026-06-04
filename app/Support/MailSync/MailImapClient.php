<?php

namespace App\Support\MailSync;

use Carbon\CarbonImmutable;
use RuntimeException;

final class MailImapClient
{
    /**
     * @var resource|null
     */
    private $connection = null;

    private ?string $connectedFolder = null;

    public function extensionLoaded(): bool
    {
        return function_exists('imap_open');
    }

    /**
     * @return list<ImportedMailMessage>
     */
    public function fetchSince(
        string $username,
        string $password,
        string $folder,
        string $direction,
        CarbonImmutable $since,
        int $limit,
    ): array {
        if (! $this->extensionLoaded()) {
            throw new RuntimeException('PHP extension imap не установлена. Установите ext-imap на сервере.');
        }

        $this->openFolder($username, $password, $folder);

        $searchDate = $since->format('d-M-Y');
        /** @var list<int>|false $uids */
        $uids = imap_search($this->connection, 'SINCE "'.$searchDate.'"', SE_UID);

        if ($uids === false) {
            return [];
        }

        rsort($uids);
        $uids = array_slice($uids, 0, $limit);

        $messages = [];

        foreach ($uids as $uid) {
            $imported = $this->parseMessage((int) $uid, $direction, $folder);

            if ($imported !== null) {
                $messages[] = $imported;
            }
        }

        return $messages;
    }

    public function disconnect(): void
    {
        if (is_resource($this->connection)) {
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
        if (is_resource($this->connection) && $this->connectedFolder === $folder) {
            return;
        }

        $this->disconnect();

        $mailbox = sprintf(
            '{%s:%d/imap/%s%s}%s',
            config('mail_sync.imap.host'),
            (int) config('mail_sync.imap.port', 993),
            config('mail_sync.imap.encryption', 'ssl'),
            config('mail_sync.imap.validate_cert', true) ? '' : '/novalidate-cert',
            $folder,
        );

        $connection = imap_open($mailbox, $username, $password, OP_READONLY, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if ($connection === false) {
            throw new RuntimeException(trim((string) imap_last_error()) ?: 'Не удалось подключиться к IMAP.');
        }

        $this->connection = $connection;
        $this->connectedFolder = $folder;
    }

    private function parseMessage(int $uid, string $direction, string $folder): ?ImportedMailMessage
    {
        if (! is_resource($this->connection)) {
            return null;
        }

        $header = imap_fetchheader($this->connection, $uid, FT_UID);

        if ($header === false) {
            return null;
        }

        $internetMessageId = $this->headerValue($header, 'Message-ID');

        if ($internetMessageId === null || $internetMessageId === '') {
            return null;
        }

        $overview = imap_fetch_overview($this->connection, (string) $uid, FT_UID);
        $meta = $overview[0] ?? null;

        $fromEmail = $this->normalizeEmail($meta->from ?? '') ?? $this->headerValue($header, 'From') ?? '';
        $toRaw = $meta->to ?? $this->headerValue($header, 'To') ?? '';
        $ccRaw = $meta->cc ?? $this->headerValue($header, 'Cc') ?? '';
        $subject = isset($meta->subject) ? $this->decodeMimeHeader((string) $meta->subject) : ($this->headerValue($header, 'Subject') ?? '');
        $sentAt = null;

        if (isset($meta->date) && is_string($meta->date)) {
            try {
                $sentAt = CarbonImmutable::parse($meta->date);
            } catch (\Throwable) {
                $sentAt = null;
            }
        }

        $bodyText = $this->fetchPlainBody($uid);

        return new ImportedMailMessage(
            internetMessageId: $this->normalizeMessageId($internetMessageId),
            direction: $direction,
            fromEmail: $this->extractEmailAddress($fromEmail) ?? strtolower(trim($fromEmail)),
            toEmails: $this->extractEmailAddresses($toRaw),
            ccEmails: $this->extractEmailAddresses($ccRaw),
            subject: trim($subject),
            bodyText: $bodyText !== '' ? $bodyText : null,
            inReplyTo: $this->normalizeMessageId($this->headerValue($header, 'In-Reply-To') ?? ''),
            sentAt: $sentAt,
            folder: $folder,
        );
    }

    private function fetchPlainBody(int $uid): string
    {
        if (! is_resource($this->connection)) {
            return '';
        }

        $body = imap_fetchbody($this->connection, $uid, '1', FT_UID);

        if (! is_string($body) || $body === '') {
            $body = imap_body($this->connection, $uid, FT_UID);
        }

        if (! is_string($body)) {
            return '';
        }

        $decoded = imap_utf8($body);

        return trim(strip_tags($decoded));
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
        $decoded = imap_mime_header_decode($value);
        $parts = [];

        foreach ($decoded as $part) {
            $parts[] = $part->text ?? '';
        }

        return trim(implode('', $parts));
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
