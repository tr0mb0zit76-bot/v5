<?php

namespace App\Support;

/**
 * Приводит блок контактов EmailMaker-шаблонов КП к виду «Параллельный импорт» /
 * «Труднодоступные регионы»: {manager.name} / {manager.phone} /
 * <a href="mailto:{manager.email}">{manager.email}</a>, без серого фона #e5e5e5.
 */
final class ProposalHtmlManagerContactNormalizer
{
    /**
     * @var list<string>
     */
    private const HARDCODED_NAMES = [
        'Анатолий Шипицин',
        'Лилия Рашитова',
        'Эмиль Садыков',
    ];

    /**
     * @var list<string>
     */
    private const HARDCODED_PHONES = [
        '+7 901 940 77 22',
        '+7&nbsp;901 940 77 22',
        '+7 917 030-04-59',
        '+7&nbsp;917 030-04-59',
        '+7 917 141 70 07',
        '+7&nbsp;917 141 70 07',
    ];

    /**
     * @var list<string>
     */
    private const HARDCODED_EMAILS = [
        'sha@avtoaliyans.ru',
        'popov@log-sol.ru',
        'l.rashitova@log-sol.ru',
        'sad@log-sol.ru',
    ];

    public static function normalize(string $html): string
    {
        $html = self::stripContactGrayBackground($html);
        $html = self::replaceHardcodedNames($html);
        $html = self::replaceHardcodedPhones($html);
        $html = self::replaceHardcodedEmails($html);
        $html = self::ensureMailtoManagerEmail($html);

        return $html;
    }

    private static function stripContactGrayBackground(string $html): string
    {
        $html = (string) preg_replace('/\s*bgcolor=(["\'])#?e5e5e5\1/i', '', $html);
        $html = (string) preg_replace('/\s*background-color:\s*#e5e5e5;?/i', '', $html);

        return $html;
    }

    private static function replaceHardcodedNames(string $html): string
    {
        foreach (self::HARDCODED_NAMES as $name) {
            $html = str_replace($name, '{manager.name}', $html);
        }

        return $html;
    }

    private static function replaceHardcodedPhones(string $html): string
    {
        foreach (self::HARDCODED_PHONES as $phone) {
            $html = str_replace($phone, '{manager.phone}', $html);
        }

        return $html;
    }

    private static function replaceHardcodedEmails(string $html): string
    {
        foreach (self::HARDCODED_EMAILS as $email) {
            $quoted = preg_quote($email, '/');

            // Сохраняем кликабельный mailto, только подменяем адрес на плейсхолдер.
            $html = (string) preg_replace(
                '/<a\b([^>]*)href=(["\'])mailto:\s*'.$quoted.'\s*\2([^>]*)>\s*'.$quoted.'\s*<\/a>/iu',
                '<a$1href=$2mailto:{manager.email}$2$3>{manager.email}</a>',
                $html,
            );
            $html = (string) preg_replace(
                '/href=(["\'])mailto:\s*'.$quoted.'\s*\1/iu',
                'href=$1mailto:{manager.email}$1',
                $html,
            );
            $html = str_ireplace($email, '{manager.email}', $html);
        }

        $html = (string) preg_replace(
            '/<a\b[^>]*href=["\']maito:[^"\']*["\'][^>]*>\s*<\/a>/iu',
            '',
            $html,
        );

        return $html;
    }

    /**
     * Как в «Труднодоступные регионы»: mailto:{manager.email} + видимый текст.
     */
    private static function ensureMailtoManagerEmail(string $html): string
    {
        if (! str_contains($html, '{manager.email}')) {
            $inserted = preg_replace(
                '/(\{manager\.phone\}(?:&nbsp;|\x{00a0}|\s)*<br\s*\/?>\s*<\/strong>)/u',
                '$1<a href="mailto:{manager.email}" target="_blank">{manager.email}</a>',
                $html,
                1,
                $count,
            );

            if (is_string($inserted) && $count > 0) {
                $html = $inserted;
            }

            if (! str_contains($html, '{manager.email}')) {
                $inserted = preg_replace(
                    '/(\{manager\.phone\}<\/span>\s*<br\s*\/?>\s*<\/strong>)/u',
                    '$1<a href="mailto:{manager.email}" target="_blank">{manager.email}</a>',
                    $html,
                    1,
                    $countSpan,
                );

                if (is_string($inserted) && $countSpan > 0) {
                    $html = $inserted;
                }
            }
        }

        if (! str_contains($html, '{manager.email}')) {
            return $html;
        }

        if (str_contains($html, 'mailto:{manager.email}')) {
            return $html;
        }

        return str_replace(
            '{manager.email}',
            '<a href="mailto:{manager.email}" target="_blank">{manager.email}</a>',
            $html,
        );
    }
}
