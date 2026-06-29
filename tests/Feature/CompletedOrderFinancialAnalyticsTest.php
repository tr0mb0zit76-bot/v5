<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompletedOrderFinancialAnalytics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompletedOrderFinancialAnalyticsTest extends TestCase
{
    public function test_monthly_buckets_and_manager_stats_use_closed_orders_only(): void
    {
        $manager = User::factory()->create(['name' => 'Иван']);

        $this->insertOrderRow([
            'manager_id' => $manager->id,
            'order_date' => '2026-01-10',
            'status_updated_at' => '2026-01-15 10:00:00',
            'status' => 'closed',
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'additional_expenses' => 5000,
            'delta' => 25000,
        ]);

        $this->insertOrderRow([
            'manager_id' => $manager->id,
            'order_date' => '2026-01-20',
            'status_updated_at' => '2026-01-20 12:00:00',
            'status' => 'in_progress',
            'customer_rate' => 50000,
            'carrier_rate' => 30000,
            'additional_expenses' => 0,
            'delta' => 20000,
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);

        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-01-31');

        $buckets = $svc->monthlyBucketsForManager($manager->id, $from, $to);
        $jan = collect($buckets)->firstWhere('ym', '2026-01');
        $this->assertNotNull($jan);
        $this->assertSame(100000.0, $jan['income']);
        $this->assertSame($this->expectedOrderExpense(70000, 5000), $jan['expense']);
        $this->assertSame(25000.0, $jan['margin']);

        $managers = $svc->statsByManagers($from, $to, null);
        $this->assertCount(1, $managers);
        $this->assertSame('Иван', $managers[0]['manager_name']);
        $this->assertSame(1, $managers[0]['orders_count']);
        $this->assertSame(25000.0, $managers[0]['margin']);
        $this->assertSame(100000.0, $managers[0]['avg_check']);
    }

    public function test_monthly_buckets_use_manual_status_when_present(): void
    {
        if (! Schema::hasColumn('orders', 'manual_status')) {
            $this->markTestSkipped('Колонка manual_status недоступна.');
        }

        $manager = User::factory()->create(['name' => 'Иван']);

        $this->insertOrderRow([
            'manager_id' => $manager->id,
            'order_date' => '2026-03-10',
            'status_updated_at' => '2026-03-15 10:00:00',
            'status' => 'in_progress',
            'manual_status' => 'closed',
            'customer_rate' => 50000,
            'carrier_rate' => 30000,
            'additional_expenses' => 0,
            'delta' => 20000,
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);

        $from = Carbon::parse('2026-03-01');
        $to = Carbon::parse('2026-03-31');

        $buckets = $svc->monthlyBucketsForManager($manager->id, $from, $to);
        $march = collect($buckets)->firstWhere('ym', '2026-03');

        $this->assertNotNull($march);
        $this->assertSame(50000.0, $march['income']);
        $this->assertSame(20000.0, $march['margin']);
    }

    public function test_monthly_buckets_aggregate_includes_all_managers(): void
    {
        $managerA = User::factory()->create(['name' => 'A']);
        $managerB = User::factory()->create(['name' => 'B']);

        $this->insertOrderRow([
            'manager_id' => $managerA->id,
            'order_date' => '2026-02-01',
            'status_updated_at' => '2026-02-10 10:00:00',
            'status' => 'closed',
            'customer_rate' => 10000,
            'carrier_rate' => 6000,
            'additional_expenses' => 1000,
            'delta' => 3000,
        ]);

        $this->insertOrderRow([
            'manager_id' => $managerB->id,
            'order_date' => '2026-02-05',
            'status_updated_at' => '2026-02-12 10:00:00',
            'status' => 'closed',
            'customer_rate' => 20000,
            'carrier_rate' => 15000,
            'additional_expenses' => 0,
            'delta' => 5000,
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);
        $from = Carbon::parse('2026-02-01');
        $to = Carbon::parse('2026-02-28');

        $feb = collect($svc->monthlyBucketsAggregate($from, $to))->firstWhere('ym', '2026-02');
        $this->assertNotNull($feb);
        $this->assertSame(30000.0, $feb['income']);
        $this->assertSame($this->expectedOrderExpense(21000, 1000), $feb['expense']);
        $this->assertSame(8000.0, $feb['margin']);
    }
}
