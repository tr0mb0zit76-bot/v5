<?php

namespace App\Support\MailSync;

final class MailHtmlSanitizer
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $html = self::stripStyleAndScriptBlocks($html);
        $html = preg_replace('/\s(on\w+|xmlns|formaction)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\')/iu', '', $html) ?? $html;

        $sanitized = MailUtf8Sanitizer::sanitize($html);

        return $sanitized !== '' ? $sanitized : null;
    }

    public static function toPlainText(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $html = self::stripStyleAndScriptBlocks($html);
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/?\s*(p|div|tr|li|h[1-6]|table|blockquote)\b[^>]*>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return self::cleanPlainText($text);
    }

    /**
     * Нормализация уже извлечённого plain-текста (IMAP text/plain, превью, MCP).
     */
    public static function cleanPlainText(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $text = self::stripCssLikeContent($text);
        $text = self::stripMailingBoilerplate($text);
        $text = self::dedupeAtiCounterOffer($text);
        $text = self::formatAtiOfferLabels($text);
        $text = self::normalizeWhitespace($text);

        return MailUtf8Sanitizer::sanitize(trim($text));
    }

    /**
     * Чем выше score — тем больше «шума» (CSS, пустые строки).
     */
    public static function noiseScore(string $text): int
    {
        if ($text === '') {
            return PHP_INT_MAX;
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $cssLines = 0;
        $contentChars = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (self::looksLikeCssLine($trimmed)) {
                $cssLines++;

                continue;
            }

            $contentChars += mb_strlen($trimmed);
        }

        return ($cssLines * 1000) + max(0, 200 - $contentChars);
    }

    private static function stripStyleAndScriptBlocks(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;

        return preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
    }

    private static function stripCssLikeContent(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $filtered[] = '';

                continue;
            }

            if (self::looksLikeCssLine($trimmed)) {
                continue;
            }

            $filtered[] = $line;
        }

        return implode("\n", $filtered);
    }

    private static function looksLikeCssLine(string $line): bool
    {
        if (preg_match('/^\s*\}\s*$/', $line)) {
            return true;
        }

        if (preg_match('/^\s*@(?:media|font-face|import)\s/', $line)) {
            return true;
        }

        if (preg_match('/\{\s*$/', $line) && self::looksLikeCssSelectorLine($line)) {
            return true;
        }

        if (preg_match('/^\s*[.#][\w-]+\s*\{/', $line)) {
            return true;
        }

        if (preg_match('/^\s*(img|a|table|td|body|html|#outlook)\s*\{/', $line)) {
            return true;
        }

        if (preg_match('/^\s*[\w#.\s,\-:>+~[\]()="\']+\{\s*$/iu', $line) && preg_match('/[#.]|^(html|body|table|td|tr|a|img|ul|ol)\b/i', $line)) {
            return true;
        }

        if (preg_match('/^\s*[\w-]+\s*:\s*.+;\s*$/', $line) && ! preg_match('/^(subject|from|to|date|re|fw|fwd|ставка|фирма|контакт|моб|e-mail):/iu', $line)) {
            return true;
        }

        return false;
    }

    private static function looksLikeCssSelectorLine(string $line): bool
    {
        $selector = trim(preg_replace('/\{.*$/', '', $line) ?? $line);

        if ($selector === '') {
            return false;
        }

        if (preg_match('/^(html|body|table|td|tr|th|a|img|ul|ol|li|div|span|p)\b/i', $selector)) {
            return true;
        }

        if (preg_match('/[#.]/', $selector)) {
            return preg_match('/^[\s#.\w\-,\s:*>+~[\]()="\']+$/iu', $selector) === 1;
        }

        return false;
    }

    private static function stripMailingBoilerplate(string $text): string
    {
        $markers = [
            'Не отвечайте на это сообщение',
            'Вы получили это письмо, потому что подписались на рассылки ATI.SU',
            'Вы получили это письмо, потому что подписались',
            'Отписаться от всех рассылок',
            'Управлять подписками можно в настройках уведомлений',
            'Круглосуточная служба поддержки:',
            '© ATI.SU',
        ];

        $cutAt = null;

        foreach ($markers as $marker) {
            $pos = mb_stripos($text, $marker);

            if ($pos !== false && $pos > 0 && ($cutAt === null || $pos < $cutAt)) {
                $cutAt = $pos;
            }
        }

        if ($cutAt !== null) {
            $text = mb_substr($text, 0, $cutAt);
        }

        $text = preg_replace('/\R\s*Все предложения на Груз\s*$/u', '', $text) ?? $text;
        $text = preg_replace('/\R\s*Написать сообщение\s*$/u', '', $text) ?? $text;

        return trim($text);
    }

    private static function dedupeAtiCounterOffer(string $text): string
    {
        if (! preg_match('/Встречное предложение на груз\s*:/ui', $text)) {
            return $text;
        }

        if (preg_match_all('/Встречное предложение на груз\s*:[^\r\n]*/ui', $text, $matches) !== false && count($matches[0]) > 1) {
            $best = '';

            foreach ($matches[0] as $candidate) {
                $candidate = trim($candidate);

                if (mb_strlen($candidate) > mb_strlen($best)) {
                    $best = $candidate;
                }
            }

            if ($best !== '' && str_contains($best, 'Ставка:')) {
                if (preg_match('/('.preg_quote($best, '/').'.*?)(?:\R\s*Фирма:|\R\s*Контакт:|\z)/su', $text, $block)) {
                    return trim($block[1]);
                }

                return $best;
            }
        }

        return $text;
    }

    private static function formatAtiOfferLabels(string $text): string
    {
        if (! preg_match('/Встречное предложение на груз\s*:/ui', $text)) {
            return $text;
        }

        $labels = ['Ставка:', 'Доп. информация:', 'Фирма:', 'Контакт:', 'Моб.:', 'E-mail:'];

        foreach ($labels as $label) {
            $text = preg_replace('/(?<=[^\r\n])'.preg_quote($label, '/').'/u', "\n".$label, $text) ?? $text;
        }

        return $text;
    }

    private static function normalizeWhitespace(string $text): string
    {
        $text = preg_replace("/[ \t]+\R/u", "\n", $text) ?? $text;

        return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    }
}
