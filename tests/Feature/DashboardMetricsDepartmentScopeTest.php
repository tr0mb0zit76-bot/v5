<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardMetricsDepartmentScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedules',
            'orders',
            'department_user',
            'departments',
            'users',
            'roles',
        ]);
    }

    public function test_supervisor_sees_department_customer_returns_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $this->seedDepartmentScopeFixtures();

            $supervisor = User::query()->findOrFail(10);

            $metrics = app(DashboardMetricsService::class)->forDashboard(
                $supervisor,
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
        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $this->seedDepartmentScopeFixtures();

            DB::table('users')->where('id', 10)->update(['sees_company_dashboard' => true]);
            $supervisor = User::query()->findOrFail(10);
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

    private function seedDepartmentScopeFixtures(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->foreignId('role_id')->nullable();
            $table->boolean('sees_company_dashboard')->default(false);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('department_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_approvals')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_number')->default('T-1');
            $table->date('order_date');
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('party', 20);
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->nullable();
            $table->date('planned_date')->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'supervisor',
            'display_name' => 'Руководитель',
            'visibility_areas' => json_encode(['dashboard', 'dashboard_tiles'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['dashboard_tiles' => 'department'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departments')->insert([
            ['id' => 1, 'name' => 'Продажи', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Логистика', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $now = now();
        foreach ([
            ['id' => 10, 'name' => 'Руководитель', 'email' => 'sup@test.local', 'role_id' => 1],
            ['id' => 11, 'name' => 'Менеджер А', 'email' => 'a@test.local', 'role_id' => null],
            ['id' => 12, 'name' => 'Менеджер Б', 'email' => 'b@test.local', 'role_id' => null],
        ] as $user) {
            DB::table('users')->insert([
                ...$user,
                'password' => 'x',
                'sees_company_dashboard' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('department_user')->insert([
            ['user_id' => 10, 'department_id' => 1, 'is_primary' => true, 'receives_approvals' => true, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 11, 'department_id' => 1, 'is_primary' => true, 'receives_approvals' => false, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 12, 'department_id' => 2, 'is_primary' => true, 'receives_approvals' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('orders')->insert([
            ['id' => 1, 'manager_id' => 11, 'order_number' => 'A-1', 'order_date' => '2026-01-01', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'manager_id' => 12, 'order_number' => 'B-1', 'order_date' => '2026-01-01', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('payment_schedules')->insert([
            [
                'order_id' => 1,
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
                'order_id' => 2,
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
    }
}
