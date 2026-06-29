<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardMetricsWeeklyCustomerReturnsTest extends TestCase
{
    public function test_weekly_customer_return_totals_sum_overdue_and_due_until_week_end(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $manager = User::factory()->create();

            $orderId = $this->insertOrderRow([
                'manager_id' => $manager->id,
                'order_number' => 'O-1',
                'order_date' => '2026-01-01',
            ]);

            $now = now();
            $rows = [
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 100, 'planned_date' => '2026-05-02', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 50, 'planned_date' => '2026-05-05', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 200, 'planned_date' => '2026-05-07', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 30, 'planned_date' => '2026-05-10', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 999, 'planned_date' => '2026-05-11', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
                ['order_id' => $orderId, 'party' => 'customer', 'type' => 'prepayment', 'amount' => 777, 'planned_date' => '2026-05-08', 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ];
            foreach ($rows as $row) {
                DB::table('payment_schedules')->insert($row);
            }

            $svc = $this->app->make(DashboardMetricsService::class);
            $method = new ReflectionMethod(DashboardMetricsService::class, 'weeklyCustomerReturnDueTotals');
            $method->setAccessible(true);
            /** @var array{total: float, overdue: float} $result */
            $result = $method->invoke($svc, ['mode' => 'managers', 'ids' => [$manager->id]]);

            $this->assertSame(380.0, $result['total']);
            $this->assertSame(150.0, $result['overdue']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_weekly_customer_return_totals_use_outstanding_amount_when_remaining_is_zero_but_unpaid(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        Carbon::setTestNow(Carbon::parse('2026-05-06 14:00:00'));

        try {
            $manager = User::factory()->create();

            $orderId = $this->insertOrderRow([
                'manager_id' => $manager->id,
                'order_number' => 'O-1',
                'order_date' => '2026-01-01',
            ]);

            $now = now();
            DB::table('payment_schedules')->insert([
                'order_id' => $orderId,
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
            $result = $method->invoke($svc, ['mode' => 'all']);

            $this->assertSame(450_000.0, $result['overdue']);
            $this->assertSame(450_000.0, $result['total']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
