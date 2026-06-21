<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderCompensationService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCompensationServiceOrderIdSelectTest extends TestCase
{
    public function test_recalculate_manager_period_query_keeps_order_primary_key(): void
    {
        $order = Order::query()->whereNotNull('manager_id')->whereNotNull('order_date')->first();

        if ($order === null) {
            $this->markTestSkipped('Order with manager and date required.');
        }

        $orders = Order::query()
            ->where('manager_id', $order->manager_id)
            ->whereBetween('order_date', [
                $order->order_date->copy()->startOfMonth()->toDateString(),
                $order->order_date->copy()->endOfMonth()->toDateString(),
            ])
            ->when(
                Schema::hasTable('financial_terms'),
                fn ($query) => $query->with('financialTerms'),
            )
            ->orderBy('id')
            ->get();

        $this->assertNotEmpty($orders);

        foreach ($orders as $loadedOrder) {
            $this->assertGreaterThan(0, (int) $loadedOrder->getKey(), 'Order primary key must be loaded for compensation sync.');
        }
    }

    public function test_recalculate_manager_period_does_not_throw_for_existing_order(): void
    {
        $order = Order::query()->whereNotNull('manager_id')->whereNotNull('order_date')->first();

        if ($order === null) {
            $this->markTestSkipped('Order with manager and date required.');
        }

        app(OrderCompensationService::class)->recalculateManagerPeriod(
            (int) $order->manager_id,
            $order->order_date->toDateString(),
        );

        $this->assertTrue(true);
    }
}
