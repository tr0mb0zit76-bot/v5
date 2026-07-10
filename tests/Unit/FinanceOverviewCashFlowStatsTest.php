<?php

namespace Tests\Unit;

use App\Services\Finance\FinanceOverviewService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceOverviewCashFlowStatsTest extends TestCase
{
    public function test_cash_flow_stats_use_row_amount_when_remaining_is_zero_but_status_open(): void
    {
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'AB-43',
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
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

        $stats = app(FinanceOverviewService::class)->cashFlowStats(null);

        $this->assertSame(28000.0, $stats['receivables']['overdue']);
        $this->assertSame(22000.0, $stats['payables']['overdue']);
        $this->assertSame(28000.0, $stats['receivables']['total']);
        $this->assertSame(22000.0, $stats['payables']['total']);
    }

    public function test_cash_flow_journal_maps_positive_remaining_when_set(): void
    {
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'AB-99',
            'customer_id' => $customerId,
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
            ->cashFlowJournal(null)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(40000.0, $row['remaining_amount']);
        $this->assertSame(40000.0, $row['amount_due']);
        $this->assertTrue($row['is_partially_settled']);
    }
}
