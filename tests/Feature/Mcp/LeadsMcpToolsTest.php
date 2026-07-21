<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateLeadNextStepTool;
use App\Mcp\Tools\GetLeadTool;
use App\Mcp\Tools\SearchLeadsTool;
use App\Mcp\Tools\UpdateLeadFieldTool;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadsMcpToolsTest extends TestCase
{
    public function test_search_leads_respects_manager_scope(): void
    {
        $managerA = $this->makeUserWithLeadsAccess(['name' => 'Lead Manager A']);
        $managerB = $this->makeUserWithLeadsAccess(['name' => 'Lead Manager B', 'email' => 'lead-b@example.com']);

        Lead::factory()->create([
            'number' => 'LD-MCP-VISIBLE',
            'title' => 'Visible MCP Lead',
            'responsible_id' => $managerA->id,
        ]);

        Lead::factory()->create([
            'number' => 'LD-MCP-HIDDEN',
            'title' => 'Hidden MCP Lead',
            'responsible_id' => $managerB->id,
        ]);

        $response = CrmServer::actingAs($managerA)->tool(SearchLeadsTool::class, [
            'query' => 'LD-MCP',
            'limit' => 10,
        ]);

        $response
            ->assertOk()
            ->assertSee('LD-MCP-VISIBLE')
            ->assertDontSee('LD-MCP-HIDDEN');
    }

    public function test_get_lead_returns_card_and_brief(): void
    {
        $user = $this->makeUserWithLeadsAccess();

        $lead = Lead::factory()->create([
            'number' => 'LD-MCP-DETAIL',
            'title' => 'Detail MCP Lead',
            'responsible_id' => $user->id,
            'status' => 'qualification',
        ]);

        $response = CrmServer::actingAs($user)->tool(GetLeadTool::class, [
            'lead_id' => $lead->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('LD-MCP-DETAIL')
            ->assertSee('operational_brief')
            ->assertSee('wizard_path');
    }

    public function test_get_lead_denied_for_other_manager(): void
    {
        $user = $this->makeUserWithLeadsAccess();
        $other = $this->makeUserWithLeadsAccess(['email' => 'other-lead-mcp@example.com']);

        $lead = Lead::factory()->create([
            'responsible_id' => $other->id,
        ]);

        $response = CrmServer::actingAs($user)->tool(GetLeadTool::class, [
            'lead_id' => $lead->id,
        ]);

        $response->assertHasErrors();
    }

    public function test_update_lead_field_updates_title(): void
    {
        $user = $this->makeUserWithLeadsAccess();

        $lead = Lead::factory()->create([
            'title' => 'Old title',
            'responsible_id' => $user->id,
            'status' => 'new',
        ]);

        $response = CrmServer::actingAs($user)->tool(UpdateLeadFieldTool::class, [
            'lead_id' => $lead->id,
            'field' => 'title',
            'value' => 'New MCP title',
        ]);

        $response
            ->assertOk()
            ->assertSee('New MCP title');

        $this->assertSame('New MCP title', $lead->fresh()->title);
    }

    public function test_create_lead_next_step_creates_task_and_sets_next_contact(): void
    {
        if (! Schema::hasTable('tasks')) {
            $this->markTestSkipped('tasks table missing');
        }

        $user = $this->makeUserWithLeadsAndTasksAccess();

        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'next_contact_at' => null,
        ]);

        $dueAt = now()->addDays(2)->startOfHour()->toIso8601String();

        $response = CrmServer::actingAs($user)->tool(CreateLeadNextStepTool::class, [
            'lead_id' => $lead->id,
            'title' => 'Позвонить клиенту',
            'due_at' => $dueAt,
        ]);

        $response
            ->assertOk()
            ->assertSee('Позвонить клиенту');

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'title' => 'Позвонить клиенту',
            'responsible_id' => $user->id,
        ]);

        $this->assertNotNull($lead->fresh()->next_contact_at);
        $this->assertTrue(Task::query()->where('lead_id', $lead->id)->exists());
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserWithAreas(array $areas, array $scopes = [], array $overrides = []): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_leads_'.uniqid(),
            'display_name' => 'MCP Leads Test',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserWithLeadsAccess(array $overrides = []): User
    {
        return $this->makeUserWithAreas(
            ['leads', 'dashboard'],
            ['leads' => 'own'],
            $overrides,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserWithLeadsAndTasksAccess(array $overrides = []): User
    {
        return $this->makeUserWithAreas(
            ['leads', 'tasks', 'dashboard'],
            ['leads' => 'own', 'tasks' => 'own'],
            $overrides,
        );
    }
}
