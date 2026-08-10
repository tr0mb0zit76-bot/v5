<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendCommercialOutboundMailJob;
use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\CommercialMailService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendCommercialOutboundMailJobTest extends TestCase
{
    #[Test]
    public function send_outbound_stays_synchronous_by_default(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('Почтовые таблицы недоступны.');
        }

        Config::set('async.outbound_mail', false);
        Config::set('queue.default', 'database');
        Queue::fake();
        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin-sync-mail'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'sync-mail@avtoaliyans.ru',
        ]);

        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-SYNC-1',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        app(CommercialMailService::class)->sendOutbound(
            subject: 'КП тест',
            bodyText: 'Текст письма.',
            toEmails: ['client@example.com'],
            sender: $user,
            lead: $lead,
            offer: $offer,
        );

        Queue::assertNothingPushed();
        Mail::assertSent(CommercialOutboundMail::class);
        $this->assertSame('sent', $offer->fresh()->status);
    }

    #[Test]
    public function outbound_mail_dispatches_job_when_async_flag_enabled(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('Почтовые таблицы недоступны.');
        }

        Config::set('async.outbound_mail', true);
        Config::set('queue.default', 'database');
        Queue::fake();
        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin-async-mail'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'async-mail@avtoaliyans.ru',
        ]);

        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-ASYNC-1',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        $service = app(CommercialMailService::class);

        $service->sendOutbound(
            subject: 'КП тест',
            bodyText: 'Текст письма.',
            toEmails: ['client@example.com'],
            sender: $user,
            lead: $lead,
            offer: $offer,
        );

        Queue::assertPushed(SendCommercialOutboundMailJob::class, function (SendCommercialOutboundMailJob $job) use ($offer, $lead, $user): bool {
            return $job->leadOfferId === $offer->id
                && $job->leadId === $lead->id
                && $job->senderUserId === $user->id;
        });

        Mail::assertNothingSent();
        $this->assertSame('prepared', $offer->fresh()->status);
    }

    #[Test]
    public function queued_job_delivers_mail_and_finalizes_offer(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('Почтовые таблицы недоступны.');
        }

        Mail::fake();

        [$thread, $message] = $this->createOutboundFixtures();
        $user = User::query()->findOrFail($message->mailbox_user_id);
        $lead = Lead::query()->findOrFail($thread->lead_id);
        $offer = LeadOffer::query()->findOrFail($message->lead_offer_id);

        SendCommercialOutboundMailJob::dispatchSync(
            mailMessageId: $message->id,
            senderUserId: $user->id,
            toEmails: ['client@example.com'],
            ccEmails: [],
            leadOfferId: $offer->id,
            leadId: $lead->id,
            mailThreadId: $thread->id,
        );

        Mail::assertSent(CommercialOutboundMail::class);
        $this->assertSame('sent', $offer->fresh()->status);
        $this->assertNotNull($lead->fresh()->proposal_sent_at);
    }

    /**
     * @return array{0: MailThread, 1: MailMessage}
     */
    private function createOutboundFixtures(): array
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin-async-mail-fixture'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'fixture-mail@avtoaliyans.ru',
        ]);

        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-FIX-1',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        $thread = MailThread::query()->create([
            'subject' => 'КП',
            'lead_id' => $lead->id,
            'contractor_id' => $lead->counterparty_id,
            'lead_offer_id' => $offer->id,
            'last_message_at' => now(),
            'last_outbound_at' => now(),
            'mailbox_user_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_OUTBOUND,
            'from_email' => $user->email,
            'to_emails' => ['client@example.com'],
            'subject' => 'КП',
            'body_text' => 'Текст.',
            'sent_at' => now(),
            'lead_offer_id' => $offer->id,
            'created_by' => $user->id,
            'mailbox_user_id' => $user->id,
        ]);

        return [$thread, $message];
    }
}
