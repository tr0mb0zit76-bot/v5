<?php

namespace App\Support\MailSync;

final class MailUtf8Sanitizer
{
    /**
     * Приводит строку к валидному UTF-8 для utf8mb4 (тема/тело письма из IMAP).
     */
    public static function sanitize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return self::dropInvalidUtf8Bytes($value);
        }

        foreach (['Windows-1251', 'CP1251', 'KOI8-R', 'ISO-8859-5', 'ISO-8859-1'] as $charset) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $charset);

            if (! is_string($converted) || $converted === '') {
                continue;
            }

            if (mb_check_encoding($converted, 'UTF-8')) {
                return self::dropInvalidUtf8Bytes($converted);
            }
        }

        $fromLatin1 = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');

        if (is_string($fromLatin1) && $fromLatin1 !== '') {
            return self::dropInvalidUtf8Bytes($fromLatin1);
        }

        return '';
    }

    private static function dropInvalidUtf8Bytes(string $value): string
    {
        if (function_exists('iconv')) {
            $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if (is_string($cleaned)) {
                return $cleaned;
            }
        }

        $cleaned = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($cleaned) ? $cleaned : '';
    }
}
