<?php

namespace Tests\Support;

use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\RoutePoint;

trait CreatesInTransitOrders
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createInTransitOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => 'in_progress',
        ], $attributes));

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'actual_date' => now()->toDateString(),
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => null,
        ]);

        return $order->fresh(['legs.routePoints']);
    }
}
