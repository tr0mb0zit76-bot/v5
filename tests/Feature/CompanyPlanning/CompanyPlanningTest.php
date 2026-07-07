<?php

namespace Tests\Feature\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyPlanningTest extends TestCase
{
    public function test_guest_cannot_access_company_planning(): void
    {
        $this->get(route('company-planning.index'))
            ->assertRedirect(route('login'));
    }

    public function test_manager_without_management_flag_is_forbidden(): void
    {
        $manager = $this->makePlanningUser(['company_planning'], belongsToManagement: false);

        $this->actingAs($manager)
            ->get(route('company-planning.index'))
            ->assertForbidden();
    }

    public function test_management_user_can_create_initiative_with_milestone(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);

        $this->actingAs($user)
            ->post(route('company-planning.store'), [
                'title' => 'Запуск импорта',
                'direction' => 'operations',
                'goal' => 'Открыть новое направление',
                'status' => 'active',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-12-31',
                'owner_id' => $user->id,
            ])
            ->assertRedirect();

        $initiative = CompanyInitiative::query()->where('title', 'Запуск импорта')->first();
        $this->assertNotNull($initiative);

        $this->actingAs($user)
            ->post(route('company-planning.milestones.store', $initiative), [
                'title' => 'Подготовить регламент',
                'status' => 'in_progress',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-08-15',
                'progress_percent' => 50,
            ])
            ->assertRedirect(route('company-planning.show', $initiative));

        $initiative->refresh();
        $this->assertSame(50, (int) $initiative->progress_percent);
        $this->assertCount(1, $initiative->milestones);
    }

    public function test_management_user_sees_initiatives_on_index(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);

        CompanyInitiative::query()->create([
            'title' => 'Снизить дебиторку',
            'status' => 'active',
            'priority' => 'high',
            'direction' => 'finance',
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('company-planning.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CompanyPlanning/Index')
                ->has('initiatives', 1)
                ->where('initiatives.0.title', 'Снизить дебиторку')
            );
    }

    /**
     * @param  list<string>  $areas
     */
    private function makePlanningUser(array $areas, bool $belongsToManagement): User
    {
        $role = Role::query()->create([
            'name' => 'company_planning_'.uniqid(),
            'display_name' => 'Company Planning',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'belongs_to_management' => $belongsToManagement,
        ]);
    }
}
