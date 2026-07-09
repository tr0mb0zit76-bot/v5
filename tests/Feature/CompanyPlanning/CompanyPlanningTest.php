<?php

namespace Tests\Feature\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeDependency;
use App\Models\CompanyInitiativeMilestone;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\CompanyPlanning\CompanyInitiativeBudgetFactService;
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
                ->has('summary')
                ->where('view_filter', 'list')
            );
    }

    public function test_index_supports_risk_view_filter(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);

        CompanyInitiative::query()->create([
            'title' => 'Обычная инициатива',
            'status' => 'active',
            'priority' => 'normal',
            'risk_level' => 'normal',
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        CompanyInitiative::query()->create([
            'title' => 'Рискованная инициатива',
            'status' => 'active',
            'priority' => 'high',
            'risk_level' => 'high',
            'ends_on' => now()->addMonth()->toDateString(),
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('company-planning.index', ['view' => 'risk']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CompanyPlanning/Index')
                ->where('view_filter', 'risk')
                ->has('initiatives', 1)
                ->where('initiatives.0.title', 'Рискованная инициатива')
            );
    }

    public function test_index_supports_upcoming_view_filter(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);

        $initiative = CompanyInitiative::query()->create([
            'title' => 'С дедлайном этапа',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        CompanyInitiativeMilestone::query()->create([
            'company_initiative_id' => $initiative->id,
            'title' => 'Скоро',
            'status' => 'planned',
            'ends_on' => now()->addDays(3)->toDateString(),
            'sort_order' => 10,
        ]);

        CompanyInitiative::query()->create([
            'title' => 'Без срочных этапов',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('company-planning.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CompanyPlanning/Index')
                ->where('view_filter', 'upcoming')
                ->has('initiatives', 1)
                ->where('initiatives.0.title', 'С дедлайном этапа')
            );
    }

    public function test_management_user_can_reorder_milestones(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);
        $initiative = $this->makeInitiativeWithMilestones($user, ['Первый', 'Второй', 'Третий']);
        $milestones = $initiative->milestones()->orderBy('sort_order')->get();
        $reorderedIds = [
            (int) $milestones[2]->id,
            (int) $milestones[0]->id,
            (int) $milestones[1]->id,
        ];

        $this->actingAs($user)
            ->post(route('company-planning.milestones.reorder', $initiative), [
                'milestone_ids' => $reorderedIds,
            ])
            ->assertRedirect(route('company-planning.show', $initiative));

        $this->assertSame(
            $reorderedIds,
            $initiative->milestones()->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_milestone_dependency_guard_blocks_in_progress_without_predecessor(): void
    {
        if (! Schema::hasTable('company_initiative_dependencies')) {
            $this->markTestSkipped('Company planning dependency tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);
        $initiative = $this->makeInitiativeWithMilestones($user, ['Этап A', 'Этап B']);
        $milestones = $initiative->milestones()->orderBy('id')->get();

        CompanyInitiativeDependency::query()->create([
            'company_initiative_id' => $initiative->id,
            'blocked_milestone_id' => $milestones[1]->id,
            'depends_on_milestone_id' => $milestones[0]->id,
            'type' => 'finish_to_start',
        ]);

        $this->actingAs($user)
            ->patch(route('company-planning.milestones.update', $milestones[1]), [
                'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_task_done_syncs_linked_milestone(): void
    {
        if (! Schema::hasTable('company_initiatives') || ! Schema::hasColumn('tasks', 'company_initiative_milestone_id')) {
            $this->markTestSkipped('Company planning task sync columns are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning', 'tasks'], belongsToManagement: true);
        $initiative = CompanyInitiative::query()->create([
            'title' => 'С задачей',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'progress_percent' => 0,
        ]);

        $milestone = CompanyInitiativeMilestone::query()->create([
            'company_initiative_id' => $initiative->id,
            'title' => 'Этап с задачей',
            'status' => 'in_progress',
            'progress_percent' => 40,
            'sort_order' => 10,
        ]);

        $task = Task::query()->create([
            'number' => 'TSK-TEST-001',
            'title' => 'Этап с задачей',
            'status' => 'in_progress',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
            'company_initiative_id' => $initiative->id,
            'company_initiative_milestone_id' => $milestone->id,
        ]);

        $milestone->update(['task_id' => $task->id]);

        $this->actingAs($user)
            ->patchJson(route('tasks.status.update', $task), [
                'status' => 'done',
            ])
            ->assertOk();

        $milestone->refresh();
        $initiative->refresh();

        $this->assertSame('completed', $milestone->status);
        $this->assertSame(100, (int) $milestone->progress_percent);
        $this->assertSame(100, (int) $initiative->progress_percent);
    }

    public function test_management_user_can_manage_milestone_dependencies(): void
    {
        if (! Schema::hasTable('company_initiative_dependencies')) {
            $this->markTestSkipped('Company planning dependency tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);
        $initiative = $this->makeInitiativeWithMilestones($user, ['Этап A', 'Этап B', 'Этап C']);
        $milestones = $initiative->milestones()->orderBy('id')->get();
        $milestoneA = $milestones[0];
        $milestoneB = $milestones[1];
        $milestoneC = $milestones[2];

        $this->actingAs($user)
            ->post(route('company-planning.dependencies.store', $initiative), [
                'blocked_milestone_id' => $milestoneB->id,
                'depends_on_milestone_id' => $milestoneA->id,
                'notes' => 'После подготовки',
            ])
            ->assertRedirect(route('company-planning.show', $initiative));

        $dependency = CompanyInitiativeDependency::query()->first();
        $this->assertNotNull($dependency);
        $this->assertSame((int) $milestoneB->id, (int) $dependency->blocked_milestone_id);

        $this->actingAs($user)
            ->post(route('company-planning.dependencies.store', $initiative), [
                'blocked_milestone_id' => $milestoneC->id,
                'depends_on_milestone_id' => $milestoneB->id,
            ])
            ->assertRedirect(route('company-planning.show', $initiative));

        $this->actingAs($user)
            ->post(route('company-planning.dependencies.store', $initiative), [
                'blocked_milestone_id' => $milestoneA->id,
                'depends_on_milestone_id' => $milestoneC->id,
            ])
            ->assertSessionHasErrors('depends_on_milestone_id');

        $this->actingAs($user)
            ->delete(route('company-planning.dependencies.destroy', $dependency))
            ->assertRedirect(route('company-planning.show', $initiative));

        $this->assertDatabaseMissing('company_initiative_dependencies', ['id' => $dependency->id]);
    }

    public function test_show_includes_budget_snapshot_from_management_accounting(): void
    {
        if (! Schema::hasTable('company_initiatives') || ! Schema::hasTable('management_statement_lines')) {
            $this->markTestSkipped('Required tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning'], belongsToManagement: true);
        $category = $this->createManagementExpenseCategory([
            'name' => 'Инициатива тест',
            'kind' => 'overhead',
            'include_in_budget' => true,
        ]);
        $bankAccountId = $this->createManagementBankAccount()->id;

        $this->createManagementStatementLine([
            'bank_account_id' => $bankAccountId,
            'line_hash' => 'initiative-budget-fact',
            'operation_date' => '2026-08-10',
            'direction' => 'out',
            'amount' => 2500,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
        ]);

        $initiative = CompanyInitiative::query()->create([
            'title' => 'Бюджетная инициатива',
            'status' => 'active',
            'priority' => 'normal',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'planned_budget_amount' => 10000,
            'management_expense_category_id' => $category->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $snapshot = app(CompanyInitiativeBudgetFactService::class)->snapshot($initiative);
        $this->assertNotNull($snapshot);
        $this->assertSame(2500.0, $snapshot['fact_out_amount']);
        $this->assertSame(-7500.0, $snapshot['variance_amount']);

        $this->actingAs($user)
            ->get(route('company-planning.show', $initiative))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CompanyPlanning/Show')
                ->where('initiative.budget_snapshot.fact_out_amount', 2500)
                ->where('initiative.budget_snapshot.planned_amount', 10000)
            );
    }

    public function test_company_dashboard_shows_planning_portfolio_for_management_user(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            $this->markTestSkipped('Company planning tables are not migrated.');
        }

        $user = $this->makePlanningUser(['company_planning', 'dashboard'], belongsToManagement: true);
        $user->forceFill(['sees_company_dashboard' => true])->save();

        CompanyInitiative::query()->create([
            'title' => 'Портфельная инициатива',
            'status' => 'active',
            'priority' => 'high',
            'direction' => 'operations',
            'ends_on' => now()->subDay()->toDateString(),
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'risk_level' => 'high',
            'progress_percent' => 35,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('company_planning_portfolio.available', true)
                ->where('company_planning_portfolio.total_active', 1)
                ->where('company_planning_portfolio.items.0.title', 'Портфельная инициатива')
                ->where('company_planning_portfolio.items.0.is_overdue', true)
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

    /**
     * @param  list<string>  $titles
     */
    private function makeInitiativeWithMilestones(User $user, array $titles): CompanyInitiative
    {
        $initiative = CompanyInitiative::query()->create([
            'title' => 'Инициатива с этапами',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        foreach ($titles as $index => $title) {
            CompanyInitiativeMilestone::query()->create([
                'company_initiative_id' => $initiative->id,
                'title' => $title,
                'status' => 'planned',
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return $initiative->fresh(['milestones']);
    }
}
