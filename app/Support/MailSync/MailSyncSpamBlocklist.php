<?php

namespace App\Support\MailSync;

use App\Models\MailBlockedSender;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class MailSyncSpamBlocklist
{
    public const string CACHE_KEY = 'mail_sync:spam_blocklist:v1';

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isBlocked(?string $email): bool
    {
        $normalized = self::normalizeEmail($email);

        if ($normalized === '') {
            return false;
        }

        return isset(self::blockedEmails()[$normalized]);
    }

    /**
     * @return array<string, true>
     */
    private static function blockedEmails(): array
    {
        try {
            if (! Schema::hasTable('mail_blocked_senders')) {
                return [];
            }
        } catch (QueryException) {
            return [];
        }

        $ttl = max(60, (int) config('mail_sync.spam_blocklist_cache_seconds', 300));

        /** @var array<string, true> $cached */
        $cached = Cache::remember(self::CACHE_KEY, $ttl, function (): array {
            $emails = [];

            foreach (MailBlockedSender::query()->pluck('email') as $email) {
                $normalized = self::normalizeEmail(is_string($email) ? $email : null);

                if ($normalized !== '') {
                    $emails[$normalized] = true;
                }
            }

            return $emails;
        });

        return $cached;
    }

    private static function normalizeEmail(?string $email): string
    {
        if (! is_string($email)) {
            return '';
        }

        $normalized = strtolower(trim($email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : '';
    }
}
