<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardMetricsDepartmentScopeTest extends TestCase
{
    public function test_supervisor_sees_department_customer_returns_only(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('department_user')) {
            $this->markTestSkipped('Таблицы payment_schedules или department_user недоступны.');
        }

        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $fixtures = $this->seedDepartmentScopeFixtures();

            $metrics = app(DashboardMetricsService::class)->forDashboard(
                $fixtures['supervisor'],
                '2026-01-01',
                '2026-12-31',
            );

            $this->assertSame('department', $metrics['metrics_scope']);
            $this->assertSame(300.0, $metrics['weekly_client_returns']);
            $this->assertSame(300.0, $metrics['weekly_client_returns_overdue']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supervisor_with_company_dashboard_flag_sees_all_returns(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('department_user')) {
            $this->markTestSkipped('Таблицы payment_schedules или department_user недоступны.');
        }

        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $fixtures = $this->seedDepartmentScopeFixtures();

            DB::table('users')->where('id', $fixtures['supervisor']->id)->update(['sees_company_dashboard' => true]);
            $supervisor = User::query()->findOrFail($fixtures['supervisor']->id);
            $supervisor->sees_company_dashboard = true;

            $metrics = app(DashboardMetricsService::class)->forDashboard(
                $supervisor,
                '2026-01-01',
                '2026-12-31',
            );

            $this->assertSame('company', $metrics['metrics_scope']);
            $this->assertSame(450.0, $metrics['weekly_client_returns']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{supervisor: User, manager_a: User, manager_b: User}
     */
    private function seedDepartmentScopeFixtures(): array
    {
        $supervisorRoleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor-'.uniqid(),
            'display_name' => 'Руководитель',
            'visibility_areas' => json_encode(['dashboard', 'dashboard_tiles'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['dashboard_tiles' => 'department'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salesDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Продажи',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $logisticsDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Логистика',
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRoleId,
            'sees_company_dashboard' => false,
        ]);

        $managerA = User::factory()->create();
        $managerB = User::factory()->create();

        $now = now();

        DB::table('department_user')->insert([
            [
                'user_id' => $supervisor->id,
                'department_id' => $salesDepartmentId,
                'is_primary' => true,
                'receives_approvals' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $managerA->id,
                'department_id' => $salesDepartmentId,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $managerB->id,
                'department_id' => $logisticsDepartmentId,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $orderAId = $this->insertOrderRow([
            'manager_id' => $managerA->id,
            'order_number' => 'A-1',
            'order_date' => '2026-01-01',
        ]);

        $orderBId = $this->insertOrderRow([
            'manager_id' => $managerB->id,
            'order_number' => 'B-1',
            'order_date' => '2026-01-01',
        ]);

        DB::table('payment_schedules')->insert([
            [
                'order_id' => $orderAId,
                'party' => 'customer',
                'type' => 'prepayment',
                'amount' => 300,
                'paid_amount' => 0,
                'remaining_amount' => 300,
                'planned_date' => '2026-05-02',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => $orderBId,
                'party' => 'customer',
                'type' => 'prepayment',
                'amount' => 150,
                'paid_amount' => 0,
                'remaining_amount' => 150,
                'planned_date' => '2026-05-02',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [
            'supervisor' => $supervisor,
            'manager_a' => $managerA,
            'manager_b' => $managerB,
        ];
    }
}
