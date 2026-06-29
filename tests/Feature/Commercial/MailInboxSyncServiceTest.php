<?php

namespace Tests\Feature\Commercial;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\MailCounterpartyResolver;
use App\Services\Commercial\MailInboundAttachmentStorage;
use App\Services\Commercial\MailInboxSyncService;
use App\Support\MailSync\ImportedMailMessage;
use App\Support\MailSync\MailImapClient;
use App\Support\MailSync\MailImportAllowance;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MailInboxSyncServiceTest extends TestCase
{
    public function test_import_message_links_contractor_lead_and_deduplicates_by_message_id(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'Клиент',
            'email' => 'client@example.com',
        ]);

        $lead = Lead::factory()->create([
            'counterparty_id' => $contractor->id,
            'status' => 'qualification',
        ]);

        $manager = User::factory()->create([
            'email' => 'manager@avtoaliyans.ru',
        ]);

        $service = $this->syncService();

        $message = new ImportedMailMessage(
            internetMessageId: 'abc-123@mail',
            direction: MailMessage::DIRECTION_INBOUND,
            fromEmail: 'client@example.com',
            toEmails: ['manager@avtoaliyans.ru'],
            ccEmails: [],
            subject: 'Re: Запрос ставки',
            bodyText: 'Нужна перевозка Москва — Казань',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: CarbonImmutable::parse('2026-06-01 10:00:00'),
            folder: 'INBOX',
        );

        $created = $service->importMessage($manager, $message);

        $this->assertNotNull($created);
        $this->assertDatabaseHas('mail_messages', [
            'id' => $created->id,
            'internet_message_id' => 'abc-123@mail',
            'mailbox_user_id' => $manager->id,
        ]);

        $thread = $created->thread()->first();
        $this->assertNotNull($thread);
        $this->assertSame($contractor->id, $thread->contractor_id);
        $this->assertSame($lead->id, $thread->lead_id);

        $duplicate = $service->importMessage($manager, $message);
        $this->assertNull($duplicate);
        $this->assertSame(1, MailMessage::query()->count());
    }

    public function test_sync_all_returns_error_when_imap_extension_missing(): void
    {
        $client = new MailImapClient;

        if ($client->extensionLoaded()) {
            $this->markTestSkipped('Cannot assert missing ext-imap when PHP imap extension is loaded.');
        }

        $service = new MailInboxSyncService(
            $client,
            app(MailCounterpartyResolver::class),
            app(MailImportAllowance::class),
            app(ActivityLedgerService::class),
            app(MailInboundAttachmentStorage::class),
        );

        config(['mail_sync.enabled' => true]);

        $result = $service->syncAllMailboxes();

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    private function syncService(): MailInboxSyncService
    {
        return app(MailInboxSyncService::class);
    }
}
