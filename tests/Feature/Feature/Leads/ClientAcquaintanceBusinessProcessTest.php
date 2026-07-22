<?php

namespace Tests\Feature\Feature\Leads;

use App\Models\BusinessProcess;
use App\Models\Lead;
use App\Models\User;
use App\Services\BusinessProcessPlaybookSeederService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientAcquaintanceBusinessProcessTest extends TestCase
{
    public function test_client_acquaintance_process_is_available_after_migration(): void
    {
        $process = BusinessProcess::query()->where('slug', 'client-acquaintance')->first();

        $this->assertNotNull($process);
        $this->assertTrue((bool) $process->is_active);
        $this->assertSame(
            ['Выход на ЛПР', 'Диагностика и портрет', 'Следующее касание', 'Отказ', 'Готов к перевозке'],
            $process->stages()->orderBy('sequence')->pluck('name')->all(),
        );
    }

    public function test_playbook_seeder_fills_acquaintance_stages_and_links_scripts(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $result = app(BusinessProcessPlaybookSeederService::class)->seed(onlyEmpty: true);

        $this->assertGreaterThan(0, $result['stages']);

        $lprStage = BusinessProcess::query()
            ->where('slug', 'client-acquaintance')
            ->firstOrFail()
            ->stages()
            ->where('name', 'Выход на ЛПР')
            ->firstOrFail();

        $this->assertNotNull($lprStage->stage_goal);
        $this->assertNotNull($lprStage->sales_script_id);
    }

    public function test_spawn_transport_from_acquaintance_creates_child_and_closes_parent(): void
    {
        $manager = $this->createUserWithRole('manager');
        $process = BusinessProcess::query()->where('slug', 'client-acquaintance')->firstOrFail();
        $firstStage = $process->stages()->orderBy('sequence')->firstOrFail();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Холодный',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parent = Lead::query()->create([
            'number' => 'LD-ACQ-'.uniqid(),
            'status' => 'qualification',
            'source' => 'cold',
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Знакомство Агроторг',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'lead_qualification' => ['need' => 'паллеты'],
            'metadata' => [
                'acquaintance_profile' => ['routes' => 'МСК-КЗН', 'volume_forecast' => '10/мес'],
                'sales_script_capture' => ['cargo_type' => 'паллеты'],
            ],
            'business_process_id' => $process->id,
            'business_process_stage_id' => $firstStage->id,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post(route('leads.spawn-transport', $parent))
            ->assertRedirect();

        $parent->refresh();
        $childId = $parent->metadata['spawned_transport_lead_id'] ?? null;
        $this->assertNotNull($childId);

        $child = Lead::query()->findOrFail($childId);
        $this->assertSame('acquaintance_spawn', $child->source);
        $this->assertSame($contractorId, $child->counterparty_id);
        $this->assertSame('Москва', $child->loading_location);
        $this->assertSame('Казань', $child->unloading_location);
        $this->assertSame('won', $parent->status);
        $this->assertSame(
            'transport-intake',
            BusinessProcess::query()->find($child->business_process_id)?->slug,
        );
    }

    public function test_lead_show_exposes_client_acquaintance_slug(): void
    {
        $manager = $this->createUserWithRole('manager');
        $process = BusinessProcess::query()->where('slug', 'client-acquaintance')->firstOrFail();

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'business_process_id' => $process->id,
            'business_process_stage_id' => $process->stages()->orderBy('sequence')->value('id'),
        ]);

        $this->actingAs($manager)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedLead.process_progress.process_slug', 'client-acquaintance')
            );
    }

    private function createUserWithRole(string $roleName): User
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $roleName,
                'display_name' => ucfirst($roleName),
                'visibility_areas' => json_encode(['dashboard', 'leads', 'orders', 'tasks'], JSON_THROW_ON_ERROR),
                'visibility_scopes' => json_encode([
                    'leads' => $roleName === 'manager' ? 'own' : 'all',
                    'orders' => $roleName === 'manager' ? 'own' : 'all',
                    'tasks' => $roleName === 'manager' ? 'own' : 'all',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
