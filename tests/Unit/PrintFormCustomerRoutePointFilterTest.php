<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Support\OrderPrintFormContext;
use App\Support\PrintFormCustomerRoutePointFilter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PrintFormCustomerRoutePointFilterTest extends TestCase
{
    public function test_should_apply_only_for_unscoped_customer_print(): void
    {
        $this->assertFalse(PrintFormCustomerRoutePointFilter::shouldApply(null));
        $this->assertFalse(PrintFormCustomerRoutePointFilter::shouldApply(new OrderPrintFormContext(printParty: 'carrier')));
        $this->assertFalse(PrintFormCustomerRoutePointFilter::shouldApply(OrderPrintFormContext::forCustomerLeg('leg_1')));
        $this->assertTrue(PrintFormCustomerRoutePointFilter::shouldApply(new OrderPrintFormContext(printParty: 'customer')));
    }

    public function test_hides_leg_junction_hubs_for_two_leg_route_like_order_208(): void
    {
        $order = new Order;
        $leg1 = new OrderLeg(['sequence' => 1, 'description' => 'leg_1']);
        $leg1->id = 1520;
        $leg2 = new OrderLeg(['sequence' => 2, 'description' => 'leg_2']);
        $leg2->id = 1521;
        $order->setRelation('legs', new Collection([$leg1, $leg2]));

        $china = new RoutePoint([
            'order_leg_id' => 1520,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'SHIJIAZHUANG, HEBEI, CHINA',
            'normalized_data' => ['city' => 'SHIJIAZHUANG'],
        ]);
        $china->id = 3063;
        $manchuriaUnload = new RoutePoint([
            'order_leg_id' => 1520,
            'type' => 'unloading',
            'sequence' => 2,
            'address' => 'Маньчжурия',
            'normalized_data' => ['city' => 'Маньчжурия'],
        ]);
        $manchuriaUnload->id = 3065;
        $manchuriaLoad = new RoutePoint([
            'order_leg_id' => 1521,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Маньчжурия',
            'normalized_data' => ['city' => 'Маньчжурия'],
        ]);
        $manchuriaLoad->id = 3064;
        $ryazhsk = new RoutePoint([
            'order_leg_id' => 1521,
            'type' => 'unloading',
            'sequence' => 2,
            'address' => 'Рязанская обл, г Ряжск, ул Вокзальная, д 43',
            'normalized_data' => ['city' => 'Ряжск'],
        ]);
        $ryazhsk->id = 3066;

        $filtered = PrintFormCustomerRoutePointFilter::filter(
            $order,
            new Collection([$china, $manchuriaUnload, $manchuriaLoad, $ryazhsk]),
        );

        $this->assertSame([3063, 3066], $filtered->pluck('id')->all());
    }

    public function test_keeps_all_points_on_single_leg(): void
    {
        $order = new Order;
        $leg = new OrderLeg(['sequence' => 1, 'description' => 'leg_1']);
        $leg->id = 10;
        $order->setRelation('legs', new Collection([$leg]));

        $points = new Collection([
            tap(new RoutePoint(['order_leg_id' => 10, 'type' => 'loading', 'sequence' => 1, 'address' => 'A']), fn ($p) => $p->id = 1),
            tap(new RoutePoint(['order_leg_id' => 10, 'type' => 'loading', 'sequence' => 2, 'address' => 'B']), fn ($p) => $p->id = 2),
            tap(new RoutePoint(['order_leg_id' => 10, 'type' => 'unloading', 'sequence' => 3, 'address' => 'C']), fn ($p) => $p->id = 3),
            tap(new RoutePoint(['order_leg_id' => 10, 'type' => 'unloading', 'sequence' => 4, 'address' => 'D']), fn ($p) => $p->id = 4),
        ]);

        $filtered = PrintFormCustomerRoutePointFilter::filter($order, $points);

        $this->assertSame([1, 2, 3, 4], $filtered->pluck('id')->all());
    }

    public function test_three_legs_keep_first_loading_and_last_unloading_only_when_middle_is_hubs(): void
    {
        $order = new Order;
        $leg1 = new OrderLeg(['sequence' => 1]);
        $leg1->id = 1;
        $leg2 = new OrderLeg(['sequence' => 2]);
        $leg2->id = 2;
        $leg3 = new OrderLeg(['sequence' => 3]);
        $leg3->id = 3;
        $order->setRelation('legs', new Collection([$leg1, $leg2, $leg3]));

        $points = new Collection([
            tap(new RoutePoint(['order_leg_id' => 1, 'type' => 'loading', 'sequence' => 1, 'address' => 'A']), fn ($p) => $p->id = 11),
            tap(new RoutePoint(['order_leg_id' => 1, 'type' => 'unloading', 'sequence' => 2, 'address' => 'H1']), fn ($p) => $p->id = 12),
            tap(new RoutePoint(['order_leg_id' => 2, 'type' => 'loading', 'sequence' => 1, 'address' => 'H1']), fn ($p) => $p->id = 21),
            tap(new RoutePoint(['order_leg_id' => 2, 'type' => 'unloading', 'sequence' => 2, 'address' => 'H2']), fn ($p) => $p->id = 22),
            tap(new RoutePoint(['order_leg_id' => 3, 'type' => 'loading', 'sequence' => 1, 'address' => 'H2']), fn ($p) => $p->id = 31),
            tap(new RoutePoint(['order_leg_id' => 3, 'type' => 'unloading', 'sequence' => 2, 'address' => 'B']), fn ($p) => $p->id = 32),
        ]);

        $filtered = PrintFormCustomerRoutePointFilter::filter($order, $points);

        $this->assertSame([11, 32], $filtered->pluck('id')->all());
    }
}
