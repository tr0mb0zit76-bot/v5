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

    public function test_cash_flow_journal_resolves_carrier_payment_form_from_contractors_costs(): void
    {
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ИП Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-CASH-149',
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'carrier_payment_form' => null,
            'customer_payment_form' => 'no_vat',
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'contractors_costs' => json_encode([
                [
                    'contractor_id' => $carrierId,
                    'amount' => 37000,
                    'payment_form' => 'cash',
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'amount' => 37000, 'basis' => 'unloading'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 37000,
            'paid_amount' => 0,
            'remaining_amount' => 37000,
            'status' => 'pending',
            'planned_date' => now()->toDateString(),
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = app(FinanceOverviewService::class)
            ->cashFlowJournal(null)
            ->firstWhere('party', 'carrier');

        $this->assertNotNull($row);
        $this->assertSame('cash', $row['payment_form']);
        $this->assertSame('Наличка', $row['payment_form_label']);
    }
}
