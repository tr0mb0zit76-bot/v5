<?php

namespace Tests\Unit;

use App\Services\Finance\FinanceOverviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceOverviewCashFlowStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedules',
            'orders',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->string('party', 16);
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->boolean('is_partial')->default(false);
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
        });
    }

    public function test_cash_flow_stats_use_row_amount_when_remaining_is_zero_but_status_open(): void
    {
        $customerId = DB::table('contractors')->insertGetId(['name' => 'Клиент']);
        $carrierId = DB::table('contractors')->insertGetId(['name' => 'Перевозчик']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'AB-43',
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            [
                'order_id' => $orderId,
                'party' => 'customer',
                'type' => 'final',
                'amount' => 28000,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'status' => 'overdue',
                'planned_date' => '2026-05-18',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'party' => 'carrier',
                'type' => 'prepayment',
                'amount' => 22000,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'status' => 'overdue',
                'planned_date' => '2026-05-18',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = app(FinanceOverviewService::class)->cashFlowStats(null, 'admin', 'all');

        $this->assertSame(28000.0, $stats['receivables']['overdue']);
        $this->assertSame(22000.0, $stats['payables']['overdue']);
        $this->assertSame(28000.0, $stats['receivables']['total']);
        $this->assertSame(22000.0, $stats['payables']['total']);
    }

    public function test_cash_flow_journal_maps_positive_remaining_when_set(): void
    {
        $customerId = DB::table('contractors')->insertGetId(['name' => 'Клиент']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'AB-99',
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 50000,
            'paid_amount' => 10000,
            'remaining_amount' => 40000,
            'status' => 'pending',
            'planned_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = app(FinanceOverviewService::class)
            ->cashFlowJournal(null, 'admin', 'all')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(40000.0, $row['remaining_amount']);
    }
}
