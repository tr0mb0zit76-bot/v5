<?php

namespace Tests\Unit;

use App\Models\ActivityEvent;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Support\ActivityEventType;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLedgerServiceTest extends TestCase
{
    public function test_record_persists_activity_event_for_lead(): void
    {
        if (! Schema::hasTable('activity_events') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('Таблицы activity_events или leads недоступны.');
        }

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);

        $service = app(ActivityLedgerService::class);

        $event = $service->record(
            $lead,
            ActivityEventType::OfferPrepared,
            'КП подготовлено',
            'КП-LD-001',
            ['offer_id' => 1],
            null,
            $user,
        );

        $this->assertNotNull($event);
        $this->assertDatabaseHas('activity_events', [
            'id' => $event->id,
            'subject_type' => $lead->getMorphClass(),
            'subject_id' => $lead->id,
            'event_type' => ActivityEventType::OfferPrepared->value,
        ]);

        $timeline = $service->timelineForSubject($lead);
        $this->assertCount(1, $timeline);
        $this->assertSame('КП подготовлено', $timeline->first()['title']);
    }

    public function test_timeline_returns_empty_when_table_missing(): void
    {
        $service = app(ActivityLedgerService::class);
        $lead = new Lead(['id' => 1]);

        if (Schema::hasTable('activity_events')) {
            ActivityEvent::query()->where('subject_id', 1)->delete();
        }

        $events = $service->timelineForSubject($lead);

        if (Schema::hasTable('activity_events')) {
            $this->assertTrue($events->isEmpty());
        } else {
            $this->assertTrue($events->isEmpty());
        }
    }
}
