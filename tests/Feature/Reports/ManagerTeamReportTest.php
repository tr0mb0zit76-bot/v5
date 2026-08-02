<?php

namespace Tests\Feature\Reports;

use App\Models\Department;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\CompletedOrderFinancialAnalytics;
use App\Services\Reports\ManagerTeamMetricCatalog;
use App\Services\Reports\ManagerTeamReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagerTeamReportTest extends TestCase
{
    public function test_period_report_matches_closed_margin_from_legacy_analytics(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $viewer = User::factory()->create(['role_id' => $role->id, 'name' => 'РОП']);
        $manager = User::factory()->create(['role_id' => $role->id, 'name' => 'Менеджер А']);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10',
            'status' => 'closed',
            'manual_status' => 'closed',
            'status_updated_at' => '2026-04-15 12:00:00',
            'customer_rate' => 100000,
            'delta' => 25000,
        ]);

        $from = Carbon::parse('2026-04-01')->startOfDay();
        $to = Carbon::parse('2026-04-30')->endOfDay();

        $legacy = app(CompletedOrderFinancialAnalytics::class)->statsByManagers($from, $to, $viewer);
        $legacyRow = collect($legacy)->firstWhere('manager_id', $manager->id);

        $this->assertNotNull($legacyRow);

        $report = app(ManagerTeamReportService::class)->build(
            $viewer,
            ManagerTeamMetricCatalog::MODE_PERIOD,
            $from,
            $to,
            [$manager->id],
            null,
            ['orders_money', 'orders_volume'],
        );

        $row = collect($report['rows'])->firstWhere('manager_id', $manager->id);

        $this->assertNotNull($row);
        $this->assertSame((int) $legacyRow['orders_count'], (int) $row['metrics']['orders_closed']);
        $this->assertEquals((float) $legacyRow['margin'], (float) $row['metrics']['money_closed_margin']);
        $this->assertEquals((float) $legacyRow['avg_check'], (float) $row['metrics']['money_closed_avg_check']);
        $this->assertStringContainsString('Результаты периода', $report['glossary']);
    }

    public function test_period_counts_leads_orders_and_tasks(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $viewer = User::factory()->create(['role_id' => $role->id]);
        $manager = User::factory()->create(['role_id' => $role->id, 'name' => 'Иван']);

        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'created_at' => '2026-04-05 10:00:00',
            'updated_at' => '2026-04-05 10:00:00',
        ]);
        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'won',
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-04-12 10:00:00',
        ]);
        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'lost',
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-04-13 10:00:00',
        ]);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-04-08',
            'status' => 'new',
        ]);

        $task = Task::query()->create([
            'number' => 'TSK-REPORT-1',
            'title' => 'Звонок',
            'status' => 'done',
            'priority' => 'normal',
            'responsible_id' => $manager->id,
            'created_by' => $viewer->id,
            'completed_at' => '2026-04-06 09:00:00',
        ]);
        $task->forceFill(['created_at' => '2026-04-04 09:00:00'])->saveQuietly();

        $report = app(ManagerTeamReportService::class)->build(
            $viewer,
            ManagerTeamMetricCatalog::MODE_PERIOD,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            [$manager->id],
            null,
            ['leads', 'orders_volume', 'tasks'],
        );

        $row = collect($report['rows'])->firstWhere('manager_id', $manager->id);

        $this->assertSame(1, (int) $row['metrics']['leads_created']);
        $this->assertSame(1, (int) $row['metrics']['leads_won']);
        $this->assertSame(1, (int) $row['metrics']['leads_lost']);
        $this->assertEquals(50.0, (float) $row['metrics']['leads_win_rate']);
        $this->assertSame(1, (int) $row['metrics']['orders_created']);
        $this->assertSame(1, (int) $row['metrics']['tasks_created']);
        $this->assertSame(1, (int) $row['metrics']['tasks_done']);
    }

    public function test_snapshot_ignores_date_range_for_open_orders_and_pipeline_money(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $viewer = User::factory()->create(['role_id' => $role->id]);
        $manager = User::factory()->create(['role_id' => $role->id]);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2025-01-01',
            'status' => 'documents',
            'manual_status' => 'documents',
            'customer_rate' => 40000,
            'delta' => 8000,
        ]);

        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'negotiation',
        ]);

        Task::query()->create([
            'number' => 'TSK-REPORT-2',
            'title' => 'Просрочка',
            'status' => 'in_progress',
            'priority' => 'high',
            'responsible_id' => $manager->id,
            'created_by' => $viewer->id,
            'due_at' => now()->subDay(),
        ]);

        $report = app(ManagerTeamReportService::class)->build(
            $viewer,
            ManagerTeamMetricCatalog::MODE_SNAPSHOT,
            Carbon::parse('2099-01-01')->startOfDay(),
            Carbon::parse('2099-01-31')->endOfDay(),
            [$manager->id],
            null,
            ['leads', 'orders_volume', 'orders_money', 'tasks'],
        );

        $row = collect($report['rows'])->firstWhere('manager_id', $manager->id);

        $this->assertSame(1, (int) $row['metrics']['leads_open']);
        $this->assertSame(1, (int) $row['metrics']['orders_by_status.documents']);
        $this->assertSame(1, (int) $row['metrics']['orders_open_count']);
        $this->assertEquals(40000.0, (float) $row['metrics']['money_pipeline_revenue']);
        $this->assertEquals(8000.0, (float) $row['metrics']['money_pipeline_margin']);
        $this->assertSame(1, (int) $row['metrics']['tasks_open']);
        $this->assertSame(1, (int) $row['metrics']['tasks_overdue']);
        $this->assertStringContainsString('Воронка сейчас', $report['glossary']);
    }

    public function test_own_scope_manager_cannot_see_other_manager_via_user_ids(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'own',
                'leads' => 'own',
                'tasks' => 'own',
            ],
        ]);

        $self = User::factory()->create(['role_id' => $role->id, 'name' => 'Я']);
        $other = User::factory()->create(['role_id' => $role->id, 'name' => 'Чужой']);

        Order::factory()->create([
            'manager_id' => $other->id,
            'order_date' => '2026-04-10',
            'status' => 'closed',
            'manual_status' => 'closed',
            'status_updated_at' => '2026-04-15 12:00:00',
            'customer_rate' => 50000,
            'delta' => 10000,
        ]);

        $report = app(ManagerTeamReportService::class)->build(
            $self,
            ManagerTeamMetricCatalog::MODE_PERIOD,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            [$other->id],
            null,
            ['orders_money'],
        );

        $ids = collect($report['rows'])->pluck('manager_id')->all();

        $this->assertNotContains($other->id, $ids);
        $this->assertContains($self->id, $ids);
    }

    public function test_compare_mode_returns_prev_period_and_delta_payload(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $viewer = User::factory()->create(['role_id' => $role->id]);
        $manager = User::factory()->create(['role_id' => $role->id]);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-03-10',
            'status' => 'new',
        ]);
        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10',
            'status' => 'new',
        ]);
        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-04-12',
            'status' => 'new',
        ]);

        $report = app(ManagerTeamReportService::class)->build(
            $viewer,
            ManagerTeamMetricCatalog::MODE_COMPARE,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            [$manager->id],
            null,
            ['orders_created'],
        );

        $this->assertSame('2026-03-02', $report['compare_meta']['prev_from']);
        $this->assertSame('2026-03-31', $report['compare_meta']['prev_to']);

        $row = collect($report['rows'])->firstWhere('manager_id', $manager->id);
        $cell = $row['metrics']['orders_created'];

        $this->assertIsArray($cell);
        $this->assertSame(2, (int) $cell['value']);
        $this->assertSame(1, (int) $cell['prev_value']);
        $this->assertEquals(1.0, (float) $cell['delta']);
        $this->assertEquals(100.0, (float) $cell['delta_pct']);
    }

    public function test_department_filter_limits_rows(): void
    {
        if (! Schema::hasTable('departments') || ! Schema::hasTable('department_user')) {
            $this->markTestSkipped('departments tables unavailable');
        }

        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $viewer = User::factory()->create(['role_id' => $role->id, 'name' => 'Admin']);
        $inDept = User::factory()->create(['role_id' => $role->id, 'name' => 'В отделе']);
        $outDept = User::factory()->create(['role_id' => $role->id, 'name' => 'Вне отдела']);

        $department = Department::query()->create([
            'name' => 'Продажи тест',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        DB::table('department_user')->insert([
            [
                'department_id' => $department->id,
                'user_id' => $inDept->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $report = app(ManagerTeamReportService::class)->build(
            $viewer,
            ManagerTeamMetricCatalog::MODE_PERIOD,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            [],
            (int) $department->id,
            ['leads_created'],
        );

        $ids = collect($report['rows'])->pluck('manager_id')->all();

        $this->assertContains($inDept->id, $ids);
        $this->assertNotContains($outDept->id, $ids);
    }

    public function test_managers_tab_renders_team_report_via_inertia(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
            ],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'tab' => 'managers',
            'managers_mode' => 'period',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'user_ids' => [$user->id],
            'metrics' => ['leads', 'orders_money'],
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('tab', 'managers')
            ->where('team_report.mode', 'period')
            ->has('team_report.rows')
            ->has('team_report.columns')
            ->has('team_report.glossary')
            ->where('filters.managers_mode', 'period')
        );
    }

    public function test_drill_down_returns_orders_for_metric_and_blocks_foreign_manager(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['reports', 'orders', 'leads', 'tasks'],
            'visibility_scopes' => [
                'orders' => 'own',
                'leads' => 'own',
                'tasks' => 'own',
            ],
        ]);

        $self = User::factory()->create(['role_id' => $role->id]);
        $other = User::factory()->create(['role_id' => $role->id]);

        Order::factory()->create([
            'manager_id' => $self->id,
            'order_date' => '2026-04-08',
            'status' => 'new',
            'order_number' => 'ORD-DRILL-1',
        ]);

        $ok = $this->actingAs($self)->getJson(route('reports.managers.drill-down', [
            'managers_mode' => 'period',
            'metric_key' => 'orders_created',
            'manager_id' => $self->id,
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $ok->assertOk();
        $ok->assertJsonPath('entity', 'order');
        $ok->assertJsonPath('items.0.number', 'ORD-DRILL-1');

        $this->actingAs($self)->getJson(route('reports.managers.drill-down', [
            'managers_mode' => 'period',
            'metric_key' => 'orders_created',
            'manager_id' => $other->id,
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))->assertForbidden();
    }
}
