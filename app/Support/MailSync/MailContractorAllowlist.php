<?php

namespace App\Support\MailSync;

use App\Models\Contractor;
use App\Models\ContractorContact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class MailContractorAllowlist
{
    public const string CACHE_KEY = 'mail_sync:contractor_allowlist:v2';

    private const string LEGACY_CACHE_KEY = 'mail_sync:contractor_allowlist';

    /** @var array<string, true> */
    private array $exactEmails = [];

    /** @var array<string, true> */
    private array $domains = [];

    public static function cached(): self
    {
        if (! config('mail_sync.require_contractor_match', false)) {
            return new self;
        }

        $ttl = max(60, (int) config('mail_sync.allowlist_cache_seconds', 300));

        $payload = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => self::buildFresh()->toCachePayload());

        if (! is_array($payload)) {
            self::forgetCache();

            return self::buildFresh();
        }

        return self::fromCachePayload($payload);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::LEGACY_CACHE_KEY);
        Cache::forget(self::CACHE_KEY);
    }

    public static function buildFresh(): self
    {
        $allowlist = new self;

        if (! Schema::hasTable('contractors')) {
            return $allowlist;
        }

        $contractorQuery = Contractor::query()->select([
            'id',
            'email',
            'contact_person_email',
            'mail_sync_domains',
            'is_own_company',
        ]);

        if (Schema::hasColumn('contractors', 'is_active')) {
            $contractorQuery->where(function ($query): void {
                $query->where('is_active', true)->orWhereNull('is_active');
            });
        }

        foreach ($contractorQuery->cursor() as $contractor) {
            if ($contractor->is_own_company) {
                continue;
            }

            $allowlist->registerEmail($contractor->email);
            $allowlist->registerEmail($contractor->contact_person_email);
            $allowlist->registerDomains($contractor->mail_sync_domains);
        }

        if (Schema::hasTable('contractor_contacts') && Schema::hasColumn('contractor_contacts', 'email')) {
            ContractorContact::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->select(['id', 'email'])
                ->orderBy('id')
                ->chunkById(500, function ($contacts) use ($allowlist): void {
                    foreach ($contacts as $contact) {
                        $allowlist->registerEmail($contact->email);
                    }
                });
        }

        return $allowlist;
    }

    /**
     * @return array{exact_emails: list<string>, domains: list<string>}
     */
    private function toCachePayload(): array
    {
        return [
            'exact_emails' => array_keys($this->exactEmails),
            'domains' => array_keys($this->domains),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function fromCachePayload(array $payload): self
    {
        $allowlist = new self;

        foreach ($payload['exact_emails'] ?? [] as $email) {
            if (! is_string($email) || $email === '') {
                continue;
            }

            $allowlist->exactEmails[strtolower(trim($email))] = true;
        }

        foreach ($payload['domains'] ?? [] as $domain) {
            if (! is_string($domain) || $domain === '') {
                continue;
            }

            $allowlist->domains[strtolower(trim($domain))] = true;
        }

        return $allowlist;
    }

    public function isEmpty(): bool
    {
        return $this->exactEmails === [] && $this->domains === [];
    }

    public function allowsEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (isset($this->exactEmails[$normalized])) {
            return true;
        }

        $domain = substr($normalized, (int) strrpos($normalized, '@') + 1);

        return $domain !== '' && isset($this->domains[$domain]);
    }

    /**
     * @param  list<string>  $participantEmails
     */
    public function allowsAnyParticipant(array $participantEmails, string $mailboxEmail): bool
    {
        $mailbox = strtolower(trim($mailboxEmail));

        foreach ($participantEmails as $email) {
            $normalized = strtolower(trim($email));

            if ($normalized === '' || $normalized === $mailbox) {
                continue;
            }

            if ($this->allowsEmail($normalized)) {
                return true;
            }
        }

        return false;
    }

    public function registerEmail(mixed $email): void
    {
        if (! is_string($email)) {
            return;
        }

        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->exactEmails[$normalized] = true;

        $domain = substr($normalized, (int) strrpos($normalized, '@') + 1);

        if ($domain === '' || PublicMailDomainCatalog::isPublic($domain)) {
            return;
        }

        $this->domains[$domain] = true;
    }

    public function registerDomain(mixed $domain): void
    {
        $normalized = PublicMailDomainCatalog::normalizeDomain(is_string($domain) ? $domain : '');

        if ($normalized === '' || PublicMailDomainCatalog::isPublic($normalized)) {
            return;
        }

        $this->domains[$normalized] = true;
    }

    public function registerDomains(mixed $domains): void
    {
        if (! is_array($domains)) {
            return;
        }

        foreach ($domains as $domain) {
            $this->registerDomain($domain);
        }
    }
}
