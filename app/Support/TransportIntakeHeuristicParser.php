<?php

namespace App\Support;

final class TransportIntakeHeuristicParser
{
    /**
     * @return array{loading_location: string|null, unloading_location: string|null, cargo: string|null, phone: string|null, contact_name: string|null, company_name: string|null}
     */
    public static function parse(string $message): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);

        return [
            'loading_location' => self::matchFirst($text, [
                '/\bиз\s+(.+?)\s+\bв\s+/iu',
                '/\bоткуда[:\s]+(.+?)(?:\s+куда[:\s]+|$)/iu',
                '/\bпогрузк[аи][:\s]+(.+?)(?:\s+выгрузк[аи][:\s]+|$)/iu',
            ]),
            'unloading_location' => self::matchFirst($text, [
                '/\bиз\s+.+?\s+\bв\s+(.+?)(?:[,.]\s*груз\b|\s+груз\b|\s+машин[ау]\b|\s+тел\b|$)/iu',
                '/\bкуда[:\s]+(.+?)(?:\s+груз[:\s]+|$)/iu',
                '/\bвыгрузк[аи][:\s]+(.+?)(?:\s+груз[:\s]+|$)/iu',
            ]),
            'cargo' => self::matchFirst($text, [
                '/\bгруз[:\s]+(.+?)(?:[,.]?\s+тел(?:ефон)?[:\s]+|[,.]?\s+контакт[:\s]+|$)/iu',
                '/\bгруз\s+(.+?)(?:[,.]?\s+тел(?:ефон)?[:\s]+|[,.]?\s+контакт[:\s]+|$)/iu',
            ]),
            'phone' => self::matchPhone($text),
            'contact_name' => null,
            'company_name' => null,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return self::cleanMatch($matches[1] ?? null);
            }
        }

        return null;
    }

    private static function matchPhone(string $text): ?string
    {
        if (preg_match('/(?:\+7|8)[\s(.-]*\d{3}[\s).-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/u', $text, $matches) !== 1) {
            return null;
        }

        return trim($matches[0]);
    }

    private static function cleanMatch(?string $value): ?string
    {
        $cleaned = trim((string) $value, " \t\n\r\0\x0B.,;:-");

        return $cleaned === '' ? null : mb_substr($cleaned, 0, 255);
    }
}
