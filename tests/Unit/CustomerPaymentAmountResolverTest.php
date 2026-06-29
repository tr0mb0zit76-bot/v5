<?php

namespace Tests\Unit;

use App\Support\CustomerPaymentAmountResolver;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerPaymentAmountResolverTest extends TestCase
{
    #[Test]
    public function sums_root_paid_amount_when_ledger_table_exists_but_has_no_events(): void
    {
        $orderId = $this->insertOrderRow([]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 2240000,
            'paid_amount' => 1120000,
            'remaining_amount' => 1120000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paid = CustomerPaymentAmountResolver::paidForOrderUntil($orderId);

        $this->assertSame(1120000.0, $paid);
    }

    #[Test]
    public function ignores_reversed_ledger_events(): void
    {
        $orderId = $this->insertOrderRow([]);

        DB::table('payment_schedule_payment_events')->insert([
            [
                'order_id' => $orderId,
                'party' => 'customer',
                'amount' => 50000,
                'payment_date' => '2026-06-09',
                'reversed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'party' => 'customer',
                'amount' => 567230.50,
                'payment_date' => '2026-06-10',
                'reversed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $paid = CustomerPaymentAmountResolver::paidForOrderUntil($orderId);

        $this->assertSame(567230.50, $paid);
    }
}
