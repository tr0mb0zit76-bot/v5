<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LoadingPlannerProject;
use App\Models\Role;
use App\Models\TransportTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoadingPlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:2RlkzZy95xqIjfCU4N7u8beHmq38hzI5x6z3adnT9CI=']);
    }

    public function test_loading_planner_requires_authentication(): void
    {
        $this->get('/modules/how-much-fits')->assertRedirect('/login');
    }

    public function test_user_with_modules_visibility_can_open_loading_planner(): void
    {
        $user = $this->createPlannerUser('admin');

        $this
            ->actingAs($user)
            ->get('/modules/how-much-fits')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/HowMuchFits')
                ->has('projects')
                ->has('transportTemplates')
            );
    }

    public function test_supervisor_sees_managers_project_linked_to_lead(): void
    {
        if (! Schema::hasColumn('loading_planner_projects', 'lead_id')) {
            $this->markTestSkipped('loading_planner_projects.lead_id is unavailable.');
        }

        $manager = $this->createPlannerUser('manager');
        $supervisor = $this->createPlannerUser('supervisor');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Негабаритный груз',
        ]);

        $this->actingAs($manager)
            ->post(route('modules.how-much-fits.projects.store'), [
                'lead_id' => $lead->id,
                'name' => 'Расчёт негабарита',
            ])
            ->assertRedirect();

        $projectId = (int) LoadingPlannerProject::query()->where('lead_id', $lead->id)->value('id');
        $this->assertGreaterThan(0, $projectId);

        $this->actingAs($supervisor)
            ->get(route('modules.how-much-fits.index', ['lead' => $lead->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Modules/HowMuchFits')
                ->where('linkContext.lead_id', $lead->id)
                ->where('linkContext.label', 'Лид #'.($lead->number ?? $lead->id))
                ->has('projects', 1)
                ->where('projects.0.id', $projectId)
                ->where('projects.0.owner_name', $manager->name)
                ->where('projects.0.is_shared', true)
            );
    }

    public function test_manager_cannot_open_supervisors_personal_unlinked_project(): void
    {
        $manager = $this->createPlannerUser('manager');
        $supervisor = $this->createPlannerUser('supervisor');

        $personalProject = LoadingPlannerProject::query()->create([
            'user_id' => $supervisor->id,
            'name' => 'Личный черновик руководителя',
            'status' => 'draft',
        ]);

        $this->actingAs($manager)
            ->patch(route('modules.how-much-fits.projects.update', $personalProject), [
                'name' => 'Попытка захвата',
                'cargo_groups' => [[
                    'name' => 'Группа',
                    'items' => [[
                        'name' => 'Груз',
                        'quantity' => 1,
                        'length_mm' => 1000,
                        'width_mm' => 800,
                        'height_mm' => 1000,
                    ]],
                ]],
            ])
            ->assertNotFound();
    }

    public function test_supervisor_sees_managers_personal_unlinked_project_in_index(): void
    {
        $manager = $this->createPlannerUser('manager');
        $supervisor = $this->createPlannerUser('supervisor');

        $project = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'name' => 'Черновик менеджера без сделки',
            'status' => 'draft',
        ]);

        $this->actingAs($supervisor)
            ->get(route('modules.how-much-fits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Modules/HowMuchFits')
                ->where('viewerCanSeeAllProjects', true)
                ->where('projects', fn ($projects) => collect($projects)->contains(
                    fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $project->id
                        && ($row['owner_name'] ?? '') === $manager->name,
                ))
            );
    }

    public function test_supervisor_deleting_managers_project_redirects_to_remaining_project(): void
    {
        $manager = $this->createPlannerUser('manager');
        $supervisor = $this->createPlannerUser('supervisor');

        $projectToDelete = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'name' => 'Удаляемый черновик',
            'status' => 'draft',
        ]);

        $remainingProject = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'name' => 'Остаётся в списке',
            'status' => 'draft',
            'updated_at' => now()->addSecond(),
        ]);

        $this->actingAs($supervisor)
            ->from(route('modules.how-much-fits.index', ['project' => $projectToDelete->id]))
            ->delete(route('modules.how-much-fits.projects.destroy', $projectToDelete))
            ->assertRedirect(route('modules.how-much-fits.index', ['project' => $remainingProject->id]));

        $this->assertDatabaseMissing('loading_planner_projects', [
            'id' => $projectToDelete->id,
        ]);

        $this->actingAs($supervisor)
            ->get(route('modules.how-much-fits.index', ['project' => $remainingProject->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Modules/HowMuchFits')
                ->where('selectedProject.id', $remainingProject->id)
                ->where('projects', fn ($projects) => collect($projects)->contains(
                    fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $remainingProject->id,
                ) && ! collect($projects)->contains(
                    fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $projectToDelete->id,
                ))
            );
    }

    public function test_index_falls_back_to_first_project_when_selected_project_is_missing(): void
    {
        $manager = $this->createPlannerUser('manager');

        $project = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'name' => 'Единственный проект',
            'status' => 'draft',
        ]);

        $this->actingAs($manager)
            ->get(route('modules.how-much-fits.index', ['project' => 999999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Modules/HowMuchFits')
                ->where('selectedProject.id', $project->id)
            );
    }

    public function test_store_project_from_lead_seeds_default_cargo_group(): void
    {
        if (! Schema::hasColumn('loading_planner_projects', 'lead_id')) {
            $this->markTestSkipped('loading_planner_projects.lead_id is unavailable.');
        }

        $manager = $this->createPlannerUser('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
        ]);

        DB::table('lead_cargo_items')->insert([
            'lead_id' => $lead->id,
            'name' => 'Трансформатор',
            'weight_kg' => 12000,
            'package_count' => 1,
            'metadata' => json_encode([
                'length_m' => 6.5,
                'width_m' => 2.4,
                'height_m' => 3.1,
                'is_oversized' => true,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('modules.how-much-fits.projects.store'), [
                'lead_id' => $lead->id,
            ])
            ->assertRedirect();

        $project = LoadingPlannerProject::query()->where('lead_id', $lead->id)->first();
        $this->assertNotNull($project);
        $this->assertSame($manager->id, (int) $project->user_id);

        $item = DB::table('loading_cargo_items')
            ->join('loading_cargo_groups', 'loading_cargo_groups.id', '=', 'loading_cargo_items.loading_cargo_group_id')
            ->where('loading_cargo_groups.loading_planner_project_id', $project->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('Трансформатор', $item->name);
        $this->assertSame(6500, (int) $item->length_mm);
        $this->assertSame(2400, (int) $item->width_mm);
        $this->assertSame(3100, (int) $item->height_mm);
    }

    public function test_non_admin_cannot_update_system_transport_template(): void
    {
        if (! Schema::hasTable('transport_templates')) {
            $this->markTestSkipped('transport_templates table is unavailable.');
        }

        $user = $this->createPlannerUser('manager');
        $template = TransportTemplate::query()->create([
            'name' => 'Системный тент',
            'category' => 'truck',
            'length_mm' => 13600,
            'width_mm' => 2450,
            'height_mm' => 2700,
            'max_payload_kg' => 20000,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('modules.how-much-fits.transport-templates.update', $template), [
                'name' => 'Взломанный тент',
                'category' => 'truck',
                'length_mm' => 13600,
                'width_mm' => 2450,
                'height_mm' => 2700,
                'max_payload_kg' => 20000,
            ])
            ->assertForbidden();
    }

    private function createPlannerUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
        ], [
            'display_name' => ucfirst($roleName),
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['modules_how_much_fits', 'leads', 'orders', 'dashboard'],
            'visibility_scopes' => [
                'leads' => $roleName === 'manager' ? 'own' : 'all',
                'orders' => $roleName === 'manager' ? 'own' : 'all',
            ],
        ]);

        $role->update([
            'visibility_areas' => ['modules_how_much_fits', 'leads', 'orders', 'dashboard'],
            'visibility_scopes' => [
                'leads' => $roleName === 'manager' ? 'own' : 'all',
                'orders' => $roleName === 'manager' ? 'own' : 'all',
            ],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
