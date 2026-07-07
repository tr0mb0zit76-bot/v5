<?php

namespace Tests\Feature\Feature\Leads;

use App\Models\BusinessProcess;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadSource;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadGridMutationTest extends TestCase
{
    public function test_grid_field_updates_source_inline(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'source' => 'inbound',
            'status' => 'qualification',
        ]);

        $response = $this->actingAs($manager)->patchJson(route('leads.grid-field.update', $lead), [
            'field' => 'source',
            'value' => 'base_reprocessing',
        ]);

        $response->assertOk();
        $response->assertJsonPath('lead.source', 'base_reprocessing');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'source' => 'base_reprocessing',
        ]);
    }

    public function test_grid_field_rejects_status_for_lead_with_business_process(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = BusinessProcess::query()->value('id');

        if ($processId === null) {
            $this->markTestSkipped('business processes not seeded');
        }

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'business_process_id' => $processId,
        ]);

        $response = $this->actingAs($manager)->patchJson(route('leads.grid-field.update', $lead), [
            'field' => 'status',
            'value' => 'negotiation',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'qualification',
        ]);
    }

    public function test_grid_field_updates_status_without_business_process(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'business_process_id' => null,
        ]);

        $response = $this->actingAs($manager)->patchJson(route('leads.grid-field.update', $lead), [
            'field' => 'status',
            'value' => 'negotiation',
        ]);

        $response->assertOk();
        $response->assertJsonPath('lead.status', 'negotiation');
    }

    public function test_grid_field_rejects_closed_status_from_grid(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'business_process_id' => null,
        ]);

        $response = $this->actingAs($manager)->patchJson(route('leads.grid-field.update', $lead), [
            'field' => 'status',
            'value' => 'won',
        ]);

        $response->assertStatus(422);
    }

    public function test_mass_update_changes_source_for_multiple_leads(): void
    {
        $manager = $this->createUserWithRole('manager');
        $leadA = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'source' => 'inbound',
            'status' => 'qualification',
        ]);
        $leadB = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'source' => 'website',
            'status' => 'qualification',
        ]);

        $response = $this->actingAs($manager)->postJson(route('leads.mass-update'), [
            'lead_ids' => [$leadA->id, $leadB->id],
            'action' => 'source',
            'value' => 'referral',
        ]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 2);

        $this->assertDatabaseHas('leads', ['id' => $leadA->id, 'source' => 'referral']);
        $this->assertDatabaseHas('leads', ['id' => $leadB->id, 'source' => 'referral']);
    }

    public function test_mass_update_skips_status_for_leads_with_business_process(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = BusinessProcess::query()->value('id');

        if ($processId === null) {
            $this->markTestSkipped('business processes not seeded');
        }

        $withProcess = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'business_process_id' => $processId,
        ]);
        $withoutProcess = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'business_process_id' => null,
        ]);

        $response = $this->actingAs($manager)->postJson(route('leads.mass-update'), [
            'lead_ids' => [$withProcess->id, $withoutProcess->id],
            'action' => 'status',
            'value' => 'negotiation',
        ]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);
        $response->assertJsonPath('skipped_count', 1);

        $this->assertDatabaseHas('leads', ['id' => $withProcess->id, 'status' => 'qualification']);
        $this->assertDatabaseHas('leads', ['id' => $withoutProcess->id, 'status' => 'negotiation']);
    }

    public function test_mass_delete_soft_deletes_accessible_leads(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
        ]);

        $response = $this->actingAs($manager)->postJson(route('leads.mass-update'), [
            'lead_ids' => [$lead->id],
            'action' => 'delete',
        ]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);

        $this->assertNotNull($lead->fresh()?->deleted_at);
    }

    public function test_leads_index_rows_include_inline_editable_fields(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'source' => LeadSource::values()[0] ?? 'inbound',
            'business_process_id' => null,
        ]);

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leads/Index')
            ->has('leads', fn ($leads) => $leads
                ->where(fn (array $rows) => collect($rows)->contains(
                    fn (array $row): bool => $row['id'] === $lead->id
                        && $row['responsible_id'] === $manager->id
                        && in_array('source', $row['inline_editable_fields'] ?? [], true)
                        && in_array('responsible_id', $row['inline_editable_fields'] ?? [], true)
                        && in_array('status', $row['inline_editable_fields'] ?? [], true),
                ))
            )
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
