<?php

namespace Tests\Feature\SalesScripts;

use App\Models\Lead;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptPlaySessionFieldValue;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesScriptCrmLinkTest extends TestCase
{
    public function test_session_started_from_lead_prefills_capture_fields(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);
        $user = $this->makeUser();
        $lead = Lead::query()->create([
            'number' => 'LD-PREFILL-1',
            'status' => 'new',
            'source' => 'test',
            'responsible_id' => $user->id,
            'title' => 'ООО Пример',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'planned_shipping_date' => '2026-07-15',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $versionId = (int) SalesScriptVersion::query()
            ->whereHas('script', fn ($query) => $query->where('title', 'Первичный запрос ставки (экспедиция)'))
            ->value('id');

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $versionId,
                'lead_id' => $lead->id,
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->firstOrFail();
        $values = $session->fieldValues()
            ->with('captureField:id,code')
            ->get()
            ->mapWithKeys(fn ($value): array => [$value->captureField->code => $value->value])
            ->all();

        $this->assertSame('ООО Пример', $values['client_name']);
        $this->assertSame('Москва', $values['route_from']);
        $this->assertSame('Казань', $values['route_to']);
        $this->assertSame('2026-07-15', $values['loading_date']);
    }

    public function test_completed_session_can_create_and_link_new_lead(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);
        $user = $this->makeUser();
        $version = SalesScriptVersion::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $version->id,
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->firstOrFail();
        $this->storeCapture($session, [
            'route_from' => 'СПб',
            'route_to' => 'НН',
            'cargo_type' => 'сборный',
            'loading_date' => '2026-07-20',
        ]);
        $session->forceFill([
            'outcome' => 'progress',
            'notes' => 'Клиент готов обсудить маршрут.',
            'completed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->post(route('scripts.sessions.lead.create', $session), [
                'title' => 'Новый лид после звонка',
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $session->refresh();
        $lead = Lead::query()->findOrFail($session->lead_id);

        $this->assertSame('sales_script_play', $lead->source);
        $this->assertSame('Новый лид после звонка', $lead->title);
        $this->assertSame('СПб', $lead->loading_location);
        $this->assertSame('НН', $lead->unloading_location);
        $this->assertSame('сборный', $lead->lead_qualification['need'] ?? null);
        $this->assertSame('2026-07-20', $lead->planned_shipping_date?->toDateString());
        $this->assertNotNull($session->crm_synced_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'subject' => 'Итог прохождения скрипта',
        ]);
    }

    public function test_complete_session_started_from_lead_syncs_capture_and_returns_to_lead(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);
        $user = $this->makeUser();
        $lead = Lead::query()->create([
            'number' => 'LD-SYNC-1',
            'status' => 'new',
            'source' => 'test',
            'responsible_id' => $user->id,
            'title' => 'Лид для скрипта',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $versionId = (int) SalesScriptVersion::query()
            ->whereHas('script', fn ($query) => $query->where('title', 'Первичный запрос ставки (экспедиция)'))
            ->value('id');

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $versionId,
                'lead_id' => $lead->id,
                'return_to' => 'lead',
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->firstOrFail();
        $this->assertSame($lead->id, $session->lead_id);

        $this->storeCapture($session, [
            'route_from' => 'Екб',
            'route_to' => 'Тюмень',
            'cargo_type' => 'металл',
            'next_step_date' => '2026-07-25',
        ]);

        $this->actingAs($user)
            ->post(route('scripts.sessions.complete', $session), [
                'outcome' => 'progress',
                'notes' => 'Зафиксировали маршрут',
            ])
            ->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $session->refresh();

        $this->assertSame('Екб', $lead->loading_location);
        $this->assertSame('Тюмень', $lead->unloading_location);
        $this->assertSame('металл', $lead->lead_qualification['need'] ?? null);
        $this->assertSame('2026-07-25', $lead->next_contact_at?->toDateString());
        $this->assertNotNull($session->crm_synced_at);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function storeCapture(SalesScriptPlaySession $session, array $fields): void
    {
        foreach ($fields as $code => $value) {
            $fieldId = SalesScriptCaptureField::query()->where('code', $code)->value('id');
            $this->assertNotNull($fieldId, "Capture field {$code} missing");

            SalesScriptPlaySessionFieldValue::query()->updateOrCreate(
                [
                    'sales_script_play_session_id' => $session->id,
                    'sales_script_capture_field_id' => $fieldId,
                ],
                [
                    'value' => $value,
                    'captured_at_node_id' => $session->current_node_id,
                ],
            );
        }
    }

    private function makeUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'scripts_leads_'.uniqid(),
            'display_name' => 'Scripts and leads',
            'visibility_areas' => json_encode(['scripts', 'leads'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['leads' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
