<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\PeriodCalculator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PeriodCalculatorOrderOwnerScopeTest extends TestCase
{
    public function test_manager_period_stats_include_orders_where_user_is_order_owner(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = User::factory()->create();
        $dispatcher = User::factory()->create();

        Order::factory()->create([
            'manager_id' => $dispatcher->id,
            'order_owner_id' => $owner->id,
            'order_date' => '2026-03-10',
            'customer_payment_form' => 'bank',
            'carrier_payment_form' => 'bank',
        ]);

        $stats = app(PeriodCalculator::class)->getManagerPeriodStats(
            (int) $owner->id,
            '2026-03-01',
            '2026-03-15',
        );

        $this->assertSame(1, $stats['total']);
    }
}
