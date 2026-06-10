<?php

namespace App\Support\MailSync;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class MailSyncMailboxEligibility
{
    /**
     * @return list<string>
     */
    public static function allowedDomains(): array
    {
        $raw = config('mail_sync.mailbox_domains', ['avtoaliyans.ru']);

        if (! is_array($raw)) {
            return ['avtoaliyans.ru'];
        }

        $domains = [];

        foreach ($raw as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $normalized = strtolower(ltrim(trim($domain), '@'));

            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        return array_values(array_unique($domains));
    }

    public static function isEligibleEmail(string $email): bool
    {
        $domains = self::allowedDomains();

        if ($domains === []) {
            return true;
        }

        $normalized = strtolower(trim($email));
        $at = strrpos($normalized, '@');

        if ($at === false) {
            return false;
        }

        $domain = substr($normalized, $at + 1);

        return $domain !== '' && in_array($domain, $domains, true);
    }

    public static function ineligibilityReason(User $user): ?string
    {
        if (self::isEligibleEmail((string) $user->email)) {
            return null;
        }

        $allowed = implode(', ', array_map(
            static fn (string $domain): string => '@'.$domain,
            self::allowedDomains(),
        ));

        return "ящик не на корпоративном домене (sync только {$allowed});";
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyToUserQuery(Builder $query): void
    {
        $domains = self::allowedDomains();

        if ($domains === []) {
            return;
        }

        $query->where(function (Builder $inner) use ($domains): void {
            foreach ($domains as $domain) {
                $inner->orWhere('email', 'like', '%@'.$domain);
            }
        });
    }
}
