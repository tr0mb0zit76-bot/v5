<?php

namespace Tests\Unit\Support\MailSync;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Support\MailSync\ImportedMailMessage;
use App\Support\MailSync\MailContractorAllowlist;
use App\Support\MailSync\MailImportAllowance;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MailContractorAllowlistTest extends TestCase
{
    public function test_corporate_email_adds_domain_and_exact_match(): void
    {
        $allowlist = new MailContractorAllowlist;
        $allowlist->registerEmail('manager@exwill.ru');

        $this->assertTrue($allowlist->allowsEmail('manager@exwill.ru'));
        $this->assertTrue($allowlist->allowsEmail('other@exwill.ru'));
        $this->assertFalse($allowlist->allowsEmail('other@gmail.com'));
    }

    public function test_public_mail_domain_only_allows_exact_address(): void
    {
        $allowlist = new MailContractorAllowlist;
        $allowlist->registerEmail('ivan@gmail.com');

        $this->assertTrue($allowlist->allowsEmail('ivan@gmail.com'));
        $this->assertFalse($allowlist->allowsEmail('petr@gmail.com'));
    }

    public function test_explicit_mail_sync_domains_are_registered(): void
    {
        $allowlist = new MailContractorAllowlist;
        $allowlist->registerDomains(['logistics.exwill.ru', '@gmail.com']);

        $this->assertTrue($allowlist->allowsEmail('ops@logistics.exwill.ru'));
        $this->assertFalse($allowlist->allowsEmail('ops@gmail.com'));
    }

    public function test_import_allowance_skips_unknown_participants(): void
    {
        config(['mail_sync.require_contractor_match' => true]);

        $allowlist = new MailContractorAllowlist;
        $allowlist->registerEmail('client@exwill.ru');

        $checker = new MailImportAllowance;

        $allowed = new ImportedMailMessage(
            internetMessageId: '<allowed@exwill.ru>',
            direction: 'inbound',
            fromEmail: 'client@exwill.ru',
            toEmails: ['manager@company.test'],
            ccEmails: [],
            subject: 'Заявка',
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: null,
            folder: 'INBOX',
        );

        $blocked = new ImportedMailMessage(
            internetMessageId: '<blocked@news.ru>',
            direction: 'inbound',
            fromEmail: 'newsletter@marketing.ru',
            toEmails: ['manager@company.test'],
            ccEmails: [],
            subject: 'Рассылка',
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: null,
            folder: 'INBOX',
        );

        $this->assertTrue($checker->shouldImport($allowed, 'manager@company.test', $allowlist));
        $this->assertFalse($checker->shouldImport($blocked, 'manager@company.test', $allowlist));
    }

    public function test_build_fresh_includes_contractor_contact_emails_via_chunk_by_id(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'Клиент',
            'email' => null,
            'is_own_company' => false,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Менеджер',
            'email' => 'contact@exwill.ru',
        ]);

        config(['mail_sync.require_contractor_match' => true]);

        $allowlist = MailContractorAllowlist::buildFresh();

        $this->assertTrue($allowlist->allowsEmail('contact@exwill.ru'));
    }

    public function test_cached_rehydrates_array_payload_instead_of_serialized_object(): void
    {
        Cache::flush();
        config(['mail_sync.require_contractor_match' => true]);

        Cache::put(MailContractorAllowlist::CACHE_KEY, [
            'exact_emails' => ['client@exwill.ru'],
            'domains' => ['exwill.ru'],
        ], 300);

        $allowlist = MailContractorAllowlist::cached();

        $this->assertTrue($allowlist->allowsEmail('client@exwill.ru'));
        $this->assertTrue($allowlist->allowsEmail('other@exwill.ru'));
    }
}
