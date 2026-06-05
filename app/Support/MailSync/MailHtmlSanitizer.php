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

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/\s(on\w+|xmlns|formaction)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\')/iu', '', $html) ?? $html;

        $sanitized = MailUtf8Sanitizer::sanitize($html);

        return $sanitized !== '' ? $sanitized : null;
    }
}
