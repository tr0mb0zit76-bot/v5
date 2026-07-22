<?php

namespace Tests\Unit\Services\SalesScripts;

use App\Models\Lead;
use App\Models\User;
use App\Services\SalesScripts\SalesScriptCaptureLeadMapper;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesScriptCaptureLeadMapperTest extends TestCase
{
    public function test_apply_to_lead_maps_route_qualification_and_profile_fields(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('leads table is unavailable.');
        }

        $user = User::factory()->create();
        $lead = Lead::query()->create([
            'number' => 'LD-MAP-1',
            'status' => 'new',
            'source' => 'test',
            'responsible_id' => $user->id,
            'title' => 'Исходный лид',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        app(SalesScriptCaptureLeadMapper::class)->applyToLead($lead, [
            'route_from' => 'Москва',
            'route_to' => 'Казань',
            'loading_date' => '2026-08-01',
            'next_step_date' => '2026-08-05',
            'cargo_type' => 'паллеты',
            'decision_deadline' => 'до пятницы',
            'budget_window' => 'до 80 тыс',
            'decision_criteria' => 'срок подачи',
            'email' => 'ops@example.com',
            'routes' => 'МСК-КЗН',
            'volume_forecast' => '10 машин/мес',
            'payment_terms' => '14 дней',
            'current_provider' => 'конкурент X',
        ], $user->id);

        $lead->refresh();

        $this->assertSame('Москва', $lead->loading_location);
        $this->assertSame('Казань', $lead->unloading_location);
        $this->assertSame('2026-08-01', $lead->planned_shipping_date?->toDateString());
        $this->assertSame('2026-08-05', $lead->next_contact_at?->toDateString());
        $this->assertSame('паллеты', $lead->lead_qualification['need'] ?? null);
        $this->assertSame('до пятницы', $lead->lead_qualification['timeline'] ?? null);
        $this->assertSame('до 80 тыс', $lead->lead_qualification['budget'] ?? null);
        $this->assertSame('срок подачи', $lead->lead_qualification['criteria'] ?? null);
        $this->assertSame('ops@example.com', $lead->lead_qualification['email'] ?? null);
        $this->assertSame('МСК-КЗН', $lead->metadata['acquaintance_profile']['routes'] ?? null);
        $this->assertSame('10 машин/мес', $lead->metadata['acquaintance_profile']['volume_forecast'] ?? null);
        $this->assertSame('14 дней', $lead->metadata['acquaintance_profile']['payment_terms'] ?? null);
        $this->assertSame('конкурент X', $lead->metadata['acquaintance_profile']['current_provider'] ?? null);
        $this->assertSame('Москва', $lead->metadata['sales_script_capture']['route_from'] ?? null);
    }

    public function test_fields_from_lead_prefills_known_capture_codes(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('leads table is unavailable.');
        }

        $user = User::factory()->create();
        $lead = Lead::query()->create([
            'number' => 'LD-MAP-2',
            'status' => 'new',
            'source' => 'test',
            'responsible_id' => $user->id,
            'title' => 'ООО Префилл',
            'loading_location' => 'Тула',
            'unloading_location' => 'Самара',
            'planned_shipping_date' => '2026-09-10',
            'next_contact_at' => '2026-09-12 10:00:00',
            'lead_qualification' => [
                'need' => 'налив',
                'timeline' => 'на неделе',
                'budget' => '100к',
                'criteria' => 'документы',
                'email' => 'a@b.c',
            ],
            'metadata' => [
                'acquaintance_profile' => [
                    'routes' => 'Тула-Самара',
                    'volume_forecast' => '5/мес',
                    'payment_terms' => 'предоплата',
                    'current_provider' => 'Y',
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $fields = app(SalesScriptCaptureLeadMapper::class)->fieldsFromLead($lead);

        $this->assertSame('ООО Префилл', $fields['client_name']);
        $this->assertSame('Тула', $fields['route_from']);
        $this->assertSame('Самара', $fields['route_to']);
        $this->assertSame('2026-09-10', $fields['loading_date']);
        $this->assertSame('2026-09-12', $fields['next_step_date']);
        $this->assertSame('налив', $fields['cargo_type']);
        $this->assertSame('на неделе', $fields['decision_deadline']);
        $this->assertSame('100к', $fields['budget_window']);
        $this->assertSame('документы', $fields['decision_criteria']);
        $this->assertSame('a@b.c', $fields['email']);
        $this->assertSame('Тула-Самара', $fields['routes']);
        $this->assertSame('5/мес', $fields['volume_forecast']);
        $this->assertSame('предоплата', $fields['payment_terms']);
        $this->assertSame('Y', $fields['current_provider']);
    }
}
