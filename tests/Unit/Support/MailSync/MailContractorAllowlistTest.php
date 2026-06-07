<?php

namespace Tests\Unit\Support\MailSync;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Support\MailSync\ImportedMailMessage;
use App\Support\MailSync\MailContractorAllowlist;
use App\Support\MailSync\MailImportAllowance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $this->schemaDropMany(['contractor_contacts', 'contractors']);

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->json('mail_sync_domains')->nullable();
            $table->boolean('is_own_company')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

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
}
