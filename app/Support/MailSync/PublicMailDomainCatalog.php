<?php

namespace App\Support\MailSync;

final class PublicMailDomainCatalog
{
    /**
     * @return list<string>
     */
    public static function domains(): array
    {
        $fromConfig = config('mail_sync.public_mail_domains', []);

        return is_array($fromConfig) ? array_values($fromConfig) : [];
    }

    public static function isPublic(string $domain): bool
    {
        $normalized = self::normalizeDomain($domain);

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::domains(), true);
    }

    public static function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = ltrim($value, '@');

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $parts = explode('@', $value);

            return strtolower(trim((string) end($parts)));
        }

        if (str_contains($value, '://')) {
            $host = parse_url('http://'.$value, PHP_URL_HOST);

            return is_string($host) ? strtolower($host) : $value;
        }

        return $value;
    }
}
