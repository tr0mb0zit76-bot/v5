<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadOfferMailSendTest extends TestCase
{
    public function test_send_offer_email_creates_mail_records_and_ledger_event(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Почтовые или lead-таблицы недоступны.');
        }

        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-TEST',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        $response = $this->actingAs($user)->post(route('leads.offers.send-email', [$lead, $offer]), [
            'to' => ['client@example.com'],
            'subject' => 'Тест КП',
            'body' => 'Текст письма для теста.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('mail_threads', [
            'lead_id' => $lead->id,
            'lead_offer_id' => $offer->id,
        ]);

        $offer->refresh();
        $this->assertSame('sent', $offer->status);
        $this->assertNotNull($offer->sent_at);

        if (Schema::hasTable('activity_events')) {
            $this->assertDatabaseHas('activity_events', [
                'subject_id' => $lead->id,
                'event_type' => 'offer_sent',
            ]);
        }
    }
}
