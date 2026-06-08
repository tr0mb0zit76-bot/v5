<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Support\DispositionInTransitResolver;
use Tests\TestCase;

class DispositionInTransitResolverTest extends TestCase
{
    public function test_in_transit_when_loading_actual_set_and_unloading_missing(): void
    {
        $order = new Order(['id' => 1]);
        $leg = new OrderLeg(['sequence' => 0]);
        $leg->setRelation('routePoints', collect([
            new RoutePoint(['type' => 'loading', 'sequence' => 0, 'actual_date' => '2026-06-01']),
            new RoutePoint(['type' => 'unloading', 'sequence' => 1, 'actual_date' => null]),
        ]));
        $order->setRelation('legs', collect([$leg]));

        $this->assertTrue(DispositionInTransitResolver::isInTransit($order));
    }

    public function test_not_in_transit_after_unloading_actual(): void
    {
        $order = new Order(['id' => 1]);
        $leg = new OrderLeg(['sequence' => 0]);
        $leg->setRelation('routePoints', collect([
            new RoutePoint(['type' => 'loading', 'sequence' => 0, 'actual_date' => '2026-06-01']),
            new RoutePoint(['type' => 'unloading', 'sequence' => 1, 'actual_date' => '2026-06-03']),
        ]));
        $order->setRelation('legs', collect([$leg]));

        $this->assertFalse(DispositionInTransitResolver::isInTransit($order));
    }

    public function test_not_in_transit_without_loading_actual(): void
    {
        $order = new Order(['id' => 1, 'status' => 'in_progress']);
        $order->setRelation('legs', collect());

        $this->assertFalse(DispositionInTransitResolver::isInTransit($order));
    }
}
