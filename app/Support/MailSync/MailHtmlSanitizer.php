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
        $text = self::stripCssLikeContent($text);
        $text = self::normalizeWhitespace($text);

        return MailUtf8Sanitizer::sanitize(trim($text));
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
        if (preg_match('/^\s*[.#][\w-]+\s*\{/', $line)) {
            return true;
        }

        if (preg_match('/^\s*(img|a|table|td|body|html|#outlook)\s*\{/', $line)) {
            return true;
        }

        if (preg_match('/^\s*@(?:media|font-face)\s/', $line)) {
            return true;
        }

        if (preg_match('/^\s*\}\s*$/', $line)) {
            return true;
        }

        if (preg_match('/^\s*[\w-]+\s*:\s*.+;\s*$/', $line) && ! preg_match('/^(subject|from|to|date|re|fw|fwd):/i', $line)) {
            return true;
        }

        return false;
    }

    private static function normalizeWhitespace(string $text): string
    {
        $text = preg_replace("/[ \t]+\R/u", "\n", $text) ?? $text;

        return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    }
}
