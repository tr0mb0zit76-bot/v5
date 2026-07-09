<?php

namespace Tests\Feature\SalesScripts;

use App\Models\Lead;
use App\Models\SalesScriptPlaySession;
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
        $this->assertNotNull($session->crm_synced_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'subject' => 'Итог прохождения скрипта',
        ]);
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
