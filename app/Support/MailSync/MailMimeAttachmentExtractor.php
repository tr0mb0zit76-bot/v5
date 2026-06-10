<?php

namespace App\Support\MailSync;

use IMAP\Connection;

/**
 * Извлекает вложения из MIME-структуры IMAP (не inline-части text/plain и text/html).
 */
final class MailMimeAttachmentExtractor
{
    /**
     * @param  Connection|resource  $connection
     * @return list<array{filename: string, content: string, mime_type: string|null, size: int}>
     */
    public function extract($connection, int $uid): array
    {
        if (! is_resource($connection) && ! $connection instanceof Connection) {
            return [];
        }

        $structure = @imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure === false) {
            return [];
        }

        if (! isset($structure->parts) || ! is_array($structure->parts) || $structure->parts === []) {
            return $this->singlePartAttachment($connection, $uid, '1', $structure);
        }

        return $this->collectAttachments($connection, $uid, $structure->parts, '');
    }

    /**
     * @param  Connection|resource  $connection
     * @param  list<object>  $parts
     * @return list<array{filename: string, content: string, mime_type: string|null, size: int}>
     */
    private function collectAttachments($connection, int $uid, array $parts, string $prefix): array
    {
        $attachments = [];

        foreach ($parts as $index => $part) {
            $partNumber = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);

            if (isset($part->parts) && is_array($part->parts) && $part->parts !== []) {
                $attachments = [
                    ...$attachments,
                    ...$this->collectAttachments($connection, $uid, $part->parts, $partNumber),
                ];

                continue;
            }

            if (! $this->isAttachmentPart($part)) {
                continue;
            }

            $raw = imap_fetchbody($connection, $uid, $partNumber, FT_UID);

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $encoding = (int) ($part->encoding ?? 1);
            $decoded = MailMimeBodyExtractor::decodeBinaryContent($raw, $encoding);

            if ($decoded === '') {
                continue;
            }

            $attachments[] = [
                'filename' => $this->resolveFilename($part, count($attachments) + 1),
                'content' => $decoded,
                'mime_type' => $this->resolveMimeType($part),
                'size' => strlen($decoded),
            ];
        }

        return $attachments;
    }

    /**
     * @param  Connection|resource  $connection
     * @return list<array{filename: string, content: string, mime_type: string|null, size: int}>
     */
    private function singlePartAttachment($connection, int $uid, string $partNumber, object $structure): array
    {
        if (! $this->isAttachmentPart($structure)) {
            return [];
        }

        $raw = imap_fetchbody($connection, $uid, $partNumber, FT_UID);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $encoding = (int) ($structure->encoding ?? 1);
        $decoded = MailMimeBodyExtractor::decodeBinaryContent($raw, $encoding);

        if ($decoded === '') {
            return [];
        }

        return [[
            'filename' => $this->resolveFilename($structure, 1),
            'content' => $decoded,
            'mime_type' => $this->resolveMimeType($structure),
            'size' => strlen($decoded),
        ]];
    }

    private function isAttachmentPart(object $part): bool
    {
        $type = (int) ($part->type ?? -1);
        $subtype = strtolower((string) ($part->subtype ?? ''));

        if ($type === 1) {
            return false;
        }

        if ($type === 0 && in_array($subtype, ['plain', 'html'], true)) {
            return false;
        }

        if (isset($part->ifdisposition) && $part->ifdisposition && isset($part->disposition)) {
            return strcasecmp((string) $part->disposition, 'inline') !== 0;
        }

        if ($this->parameterValue($part->dparameters ?? null, 'filename') !== null) {
            return true;
        }

        if ($this->parameterValue($part->parameters ?? null, 'name') !== null) {
            return true;
        }

        return $type !== 0;
    }

    private function resolveMimeType(object $part): ?string
    {
        $type = (int) ($part->type ?? -1);
        $subtype = strtolower((string) ($part->subtype ?? ''));

        if ($type < 0 || $subtype === '') {
            return null;
        }

        $primary = match ($type) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'application',
        };

        return $primary.'/'.$subtype;
    }

    private function resolveFilename(object $part, int $sequence): string
    {
        $raw = $this->parameterValue($part->dparameters ?? null, 'filename')
            ?? $this->parameterValue($part->parameters ?? null, 'name')
            ?? ('attachment-'.$sequence);

        $decoded = $this->decodeMimeHeaderValue($raw);
        $basename = basename(str_replace('\\', '/', $decoded));
        $basename = trim($basename);

        if ($basename === '' || $basename === '.' || $basename === '..') {
            return 'attachment-'.$sequence;
        }

        return $basename;
    }

    private function decodeMimeHeaderValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        if (function_exists('imap_utf8')) {
            $decoded = @imap_utf8($value);

            if (is_string($decoded) && $decoded !== '') {
                return MailUtf8Sanitizer::sanitize($decoded);
            }
        }

        if (function_exists('imap_mime_header_decode')) {
            $parts = imap_mime_header_decode($value);

            if (is_array($parts)) {
                $joined = '';

                foreach ($parts as $part) {
                    $joined .= (string) ($part->text ?? '');
                }

                if ($joined !== '') {
                    return MailUtf8Sanitizer::sanitize($joined);
                }
            }
        }

        return MailUtf8Sanitizer::sanitize($value);
    }

    /**
     * @param  array<int, object>|object|null  $parameters
     */
    private function parameterValue(array|object|null $parameters, string $attribute): ?string
    {
        foreach ($this->normalizeParameters($parameters) as $parameter) {
            if (! isset($parameter->attribute, $parameter->value)) {
                continue;
            }

            if (strcasecmp((string) $parameter->attribute, $attribute) === 0) {
                $value = trim((string) $parameter->value);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * IMAP может вернуть parameters как массив объектов или один stdClass.
     *
     * @return list<object>
     */
    private function normalizeParameters(mixed $parameters): array
    {
        if ($parameters === null) {
            return [];
        }

        if (is_array($parameters)) {
            return array_values(array_filter($parameters, is_object(...)));
        }

        if (is_object($parameters)) {
            if (isset($parameters->attribute)) {
                return [$parameters];
            }

            return array_values(array_filter((array) $parameters, is_object(...)));
        }

        return [];
    }
}
