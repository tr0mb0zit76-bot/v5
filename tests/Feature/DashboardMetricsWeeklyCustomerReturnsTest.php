<?php

namespace Tests\Feature;

use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardMetricsWeeklyCustomerReturnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['payment_schedules', 'orders', 'users']);
    }

    public function test_weekly_customer_return_totals_sum_overdue_and_due_until_week_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
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

            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Менеджер',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->insert([
                'id' => 1,
                'manager_id' => 1,
                'order_number' => 'O-1',
                'order_date' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            $rows = [
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 100, 'planned_date' => '2026-05-02', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 50, 'planned_date' => '2026-05-05', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 200, 'planned_date' => '2026-05-07', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 30, 'planned_date' => '2026-05-10', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 999, 'planned_date' => '2026-05-11', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => 1, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 777, 'planned_date' => '2026-05-08', 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ];
            foreach ($rows as $row) {
                DB::table('payment_schedules')->insert($row);
            }

            $svc = $this->app->make(DashboardMetricsService::class);
            $method = new ReflectionMethod(DashboardMetricsService::class, 'weeklyCustomerReturnDueTotals');
            $method->setAccessible(true);
            /** @var array{total: float, overdue: float} $result */
            $result = $method->invoke($svc, 1);

            $this->assertSame(380.0, $result['total']);
            $this->assertSame(150.0, $result['overdue']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_weekly_customer_return_totals_use_outstanding_amount_when_remaining_is_zero_but_unpaid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
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

            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Менеджер',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->insert([
                'id' => 1,
                'manager_id' => 1,
                'order_number' => 'O-1',
                'order_date' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            DB::table('payment_schedules')->insert([
                'order_id' => 1,
                'party' => 'customer',
                'type' => 'prepayment',
                'amount' => 450_000,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'planned_date' => '2026-05-02',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $svc = $this->app->make(DashboardMetricsService::class);
            $method = new ReflectionMethod(DashboardMetricsService::class, 'weeklyCustomerReturnDueTotals');
            $method->setAccessible(true);
            /** @var array{total: float, overdue: float} $result */
            $result = $method->invoke($svc, null);

            $this->assertSame(450_000.0, $result['overdue']);
            $this->assertSame(450_000.0, $result['total']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
