<?php

namespace App\Support\MailSync;

use Illuminate\Support\Str;

final class OutboundMailMessageId
{
    public static function generate(string $fromEmail): string
    {
        $domain = self::domainFromEmail($fromEmail);

        return sprintf('<%s@%s>', Str::uuid()->toString(), $domain);
    }

    private static function domainFromEmail(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at !== false) {
            $domain = strtolower(substr($email, $at + 1));

            if ($domain !== '') {
                return $domain;
            }
        }

        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);

        return is_string($appHost) && $appHost !== '' ? $appHost : 'localhost';
    }
}
