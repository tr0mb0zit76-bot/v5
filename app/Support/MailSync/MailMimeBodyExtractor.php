<?php

namespace App\Support\MailSync;

use IMAP\Connection;

/**
 * Извлекает plain-текст из MIME-структуры IMAP с учётом charset и transfer-encoding.
 */
final class MailMimeBodyExtractor
{
    private const ENCODING_7BIT = 0;

    private const ENCODING_8BIT = 1;

    private const ENCODING_BINARY = 2;

    private const ENCODING_BASE64 = 3;

    private const ENCODING_QUOTED_PRINTABLE = 4;

    /**
     * @param  Connection|resource  $connection
     */
    public function extractPlainText($connection, int $uid): string
    {
        if (! is_resource($connection) && ! $connection instanceof Connection) {
            return '';
        }

        $structure = @imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure === false) {
            $fallback = imap_body($connection, $uid, FT_UID);

            return is_string($fallback)
                ? MailHtmlSanitizer::toPlainText(self::decodeTransferEncoding($fallback, self::ENCODING_8BIT))
                : '';
        }

        if (! isset($structure->parts) || ! is_array($structure->parts) || $structure->parts === []) {
            return MailHtmlSanitizer::toPlainText(
                $this->decodePart($connection, $uid, '1', $structure),
            );
        }

        $plain = $this->findPartText($connection, $uid, $structure->parts, '', 'text/plain', stripTags: true);

        if ($plain !== '') {
            $htmlPlain = $this->findPartText($connection, $uid, $structure->parts, '', 'text/html', stripTags: true);

            if ($htmlPlain !== '' && MailHtmlSanitizer::noiseScore($plain) > MailHtmlSanitizer::noiseScore($htmlPlain)) {
                return $htmlPlain;
            }

            return $plain;
        }

        $html = $this->findPartText($connection, $uid, $structure->parts, '', 'text/html', stripTags: true);

        return MailHtmlSanitizer::toPlainText($html);
    }

    /**
     * @param  Connection|resource  $connection
     */
    public function extractHtml($connection, int $uid): ?string
    {
        if (! is_resource($connection) && ! $connection instanceof Connection) {
            return null;
        }

        $structure = @imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure === false) {
            return null;
        }

        if (! isset($structure->parts) || ! is_array($structure->parts) || $structure->parts === []) {
            $subtype = strtolower((string) ($structure->subtype ?? ''));

            if ((int) ($structure->type ?? -1) === 0 && $subtype === 'html') {
                $html = trim($this->decodePart($connection, $uid, '1', $structure));

                return $html !== '' ? MailHtmlSanitizer::sanitize($html) : null;
            }

            return null;
        }

        $html = trim($this->findPartText($connection, $uid, $structure->parts, '', 'text/html', stripTags: false));

        return $html !== '' ? MailHtmlSanitizer::sanitize($html) : null;
    }

    /**
     * @param  Connection|resource  $connection
     * @param  list<object>  $parts
     */
    private function findPartText($connection, int $uid, array $parts, string $prefix, string $mimeType, bool $stripTags = true): string
    {
        $wantedSubtype = strtolower(str_replace('text/', '', $mimeType));

        foreach ($parts as $index => $part) {
            $partNumber = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);

            if (isset($part->parts) && is_array($part->parts) && $part->parts !== []) {
                $nested = $this->findPartText($connection, $uid, $part->parts, $partNumber, $mimeType, $stripTags);

                if ($nested !== '') {
                    return $nested;
                }
            }

            $type = (int) ($part->type ?? -1);
            $subtype = strtolower((string) ($part->subtype ?? ''));

            if ($type === 0 && $subtype === $wantedSubtype) {
                $decoded = $this->decodePart($connection, $uid, $partNumber, $part);

                if ($decoded !== '') {
                    return $stripTags ? MailHtmlSanitizer::toPlainText($decoded) : $decoded;
                }
            }
        }

        return '';
    }

    /**
     * @param  Connection|resource  $connection
     */
    private function decodePart($connection, int $uid, string $partNumber, object $structure): string
    {
        $raw = imap_fetchbody($connection, $uid, $partNumber, FT_UID);

        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $encoding = (int) ($structure->encoding ?? self::ENCODING_8BIT);
        $charset = $this->resolveCharset($structure);

        return self::decodeContent($raw, $encoding, $charset);
    }

    public static function decodeContent(string $body, int $encoding, ?string $charset): string
    {
        $decoded = self::decodeTransferEncoding($body, $encoding);

        return self::convertCharset($decoded, $charset);
    }

    /**
     * Декодирование бинарных частей (PDF, изображения) без charset/UTF-8 нормализации.
     */
    public static function decodeBinaryContent(string $body, int $encoding): string
    {
        return self::decodeTransferEncoding($body, $encoding);
    }

    public static function decodeTransferEncoding(string $body, int $encoding): string
    {
        return match ($encoding) {
            self::ENCODING_BASE64 => self::decodeBase64($body),
            self::ENCODING_QUOTED_PRINTABLE => self::decodeQuotedPrintable($body),
            default => $body,
        };
    }

    private static function decodeBase64(string $body): string
    {
        $normalized = preg_replace('/\s+/', '', $body) ?? $body;
        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : $body;
    }

    private static function decodeQuotedPrintable(string $body): string
    {
        if (function_exists('imap_qprint')) {
            $decoded = imap_qprint($body);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return quoted_printable_decode($body);
    }

    public static function convertCharset(string $body, ?string $charset): string
    {
        if ($body === '') {
            return '';
        }

        $charset = self::normalizeCharset($charset);

        if ($charset === null || $charset === 'utf-8' || $charset === 'utf8') {
            return MailUtf8Sanitizer::sanitize($body);
        }

        if ($charset === 'default' || $charset === 'ascii' || $charset === 'us-ascii') {
            return MailUtf8Sanitizer::sanitize($body);
        }

        $converted = @mb_convert_encoding($body, 'UTF-8', $charset);

        return MailUtf8Sanitizer::sanitize(is_string($converted) ? $converted : $body);
    }

    private function resolveCharset(object $structure): ?string
    {
        $charset = $this->parameterValue($structure->parameters ?? null, 'charset');

        if ($charset !== null) {
            return $charset;
        }

        return $this->parameterValue($structure->dparameters ?? null, 'charset');
    }

    /**
     * @param  list<object>|null  $parameters
     */
    private function parameterValue(?array $parameters, string $attribute): ?string
    {
        if ($parameters === null) {
            return null;
        }

        foreach ($parameters as $parameter) {
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

    private static function normalizeCharset(?string $charset): ?string
    {
        if ($charset === null) {
            return null;
        }

        $charset = strtolower(trim(str_replace(['"', "'"], '', $charset)));

        if ($charset === '') {
            return null;
        }

        return match ($charset) {
            'cp1251', 'windows-1251', 'win-1251' => 'Windows-1251',
            'koi8-r', 'koi8r' => 'KOI8-R',
            'iso-8859-5' => 'ISO-8859-5',
            default => $charset,
        };
    }
}
