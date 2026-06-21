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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailInboxSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'activity_events',
            'mail_messages',
            'mail_threads',
            'leads',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('mail_imap_secret')->nullable();
            $table->boolean('mail_sync_enabled')->default(true);
            $table->timestamp('mail_last_sync_at')->nullable();
            $table->string('mail_last_sync_error', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });

        Schema::create('mail_threads', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('mailbox_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mail_thread_id');
            $table->string('direction', 20);
            $table->string('internet_message_id', 500)->nullable()->unique();
            $table->string('from_email');
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->string('subject');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('mailbox_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('event_type');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_import_message_links_contractor_lead_and_deduplicates_by_message_id(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'Клиент',
            'email' => 'client@example.com',
        ]);

        $lead = Lead::query()->create([
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
        $client = $this->createMock(MailImapClient::class);
        $client->method('extensionLoaded')->willReturn(false);

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
