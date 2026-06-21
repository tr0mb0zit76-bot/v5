<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderUpdateRouteTest extends TestCase
{
    #[Test]
    public function test_orders_update_route_accepts_post_and_patch(): void
    {
        $route = app('router')->getRoutes()->getByName('orders.update');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertContains('PATCH', $route->methods());
    }

    #[Test]
    public function test_orders_inline_update_route_accepts_post_and_patch(): void
    {
        $route = app('router')->getRoutes()->getByName('orders.inline-update');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertContains('PATCH', $route->methods());
    }

    #[Test]
    public function test_orders_save_route_is_post_only(): void
    {
        $route = app('router')->getRoutes()->getByName('orders.save');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('orders/{order}/save', $route->uri());
    }

    #[Test]
    public function test_calculate_compensation_does_not_match_orders_update_route(): void
    {
        $request = Request::create('https://crm.aa.local/orders/calculate-compensation', 'POST');
        $route = app('router')->getRoutes()->match($request);

        $this->assertSame('orders.calculate-compensation', $route->getName());
    }
}
