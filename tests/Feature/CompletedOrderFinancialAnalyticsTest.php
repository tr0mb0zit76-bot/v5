<?php

namespace Tests\Feature;

use App\Services\CompletedOrderFinancialAnalytics;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompletedOrderFinancialAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['orders', 'users']);
    }

    public function test_monthly_buckets_and_manager_stats_use_closed_orders_only(): void
    {
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
            $table->timestamp('status_updated_at')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('customer_rate', 14, 2)->default(0);
            $table->decimal('carrier_rate', 14, 2)->default(0);
            $table->decimal('additional_expenses', 14, 2)->default(0);
            $table->decimal('delta', 14, 2)->default(0);
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Иван',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'manager_id' => 1,
            'order_date' => '2026-01-10',
            'status_updated_at' => '2026-01-15 10:00:00',
            'status' => 'closed',
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'additional_expenses' => 5000,
            'delta' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'manager_id' => 1,
            'order_date' => '2026-01-20',
            'status_updated_at' => '2026-01-20 12:00:00',
            'status' => 'in_progress',
            'customer_rate' => 50000,
            'carrier_rate' => 30000,
            'additional_expenses' => 0,
            'delta' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);

        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-01-31');

        $buckets = $svc->monthlyBucketsForManager(1, $from, $to);
        $jan = collect($buckets)->firstWhere('ym', '2026-01');
        $this->assertNotNull($jan);
        $this->assertSame(100000.0, $jan['income']);
        $this->assertSame(75000.0, $jan['expense']);
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
            $table->timestamp('status_updated_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('manual_status')->nullable();
            $table->decimal('customer_rate', 14, 2)->default(0);
            $table->decimal('carrier_rate', 14, 2)->default(0);
            $table->decimal('additional_expenses', 14, 2)->default(0);
            $table->decimal('delta', 14, 2)->default(0);
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Иван',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'manager_id' => 1,
            'order_date' => '2026-03-10',
            'status_updated_at' => '2026-03-15 10:00:00',
            'status' => 'in_progress',
            'manual_status' => 'closed',
            'customer_rate' => 50000,
            'carrier_rate' => 30000,
            'additional_expenses' => 0,
            'delta' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);

        $from = Carbon::parse('2026-03-01');
        $to = Carbon::parse('2026-03-31');

        $buckets = $svc->monthlyBucketsForManager(1, $from, $to);
        $march = collect($buckets)->firstWhere('ym', '2026-03');

        $this->assertNotNull($march);
        $this->assertSame(50000.0, $march['income']);
        $this->assertSame(20000.0, $march['margin']);
    }

    public function test_monthly_buckets_aggregate_includes_all_managers(): void
    {
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
            $table->timestamp('status_updated_at')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('customer_rate', 14, 2)->default(0);
            $table->decimal('carrier_rate', 14, 2)->default(0);
            $table->decimal('additional_expenses', 14, 2)->default(0);
            $table->decimal('delta', 14, 2)->default(0);
            $table->timestamps();
        });

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('orders')->insert([
            [
                'manager_id' => 1,
                'order_date' => '2026-02-01',
                'status_updated_at' => '2026-02-10 10:00:00',
                'status' => 'closed',
                'customer_rate' => 10000,
                'carrier_rate' => 6000,
                'additional_expenses' => 1000,
                'delta' => 3000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'manager_id' => 2,
                'order_date' => '2026-02-05',
                'status_updated_at' => '2026-02-12 10:00:00',
                'status' => 'closed',
                'customer_rate' => 20000,
                'carrier_rate' => 15000,
                'additional_expenses' => 0,
                'delta' => 5000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $svc = $this->app->make(CompletedOrderFinancialAnalytics::class);
        $from = Carbon::parse('2026-02-01');
        $to = Carbon::parse('2026-02-28');

        $feb = collect($svc->monthlyBucketsAggregate($from, $to))->firstWhere('ym', '2026-02');
        $this->assertNotNull($feb);
        $this->assertSame(30000.0, $feb['income']);
        $this->assertSame(22000.0, $feb['expense']);
        $this->assertSame(8000.0, $feb['margin']);
    }
}
