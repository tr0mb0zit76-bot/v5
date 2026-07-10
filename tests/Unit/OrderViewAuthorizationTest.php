<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderViewAuthorizationTest extends TestCase
{
    public function test_order_owner_with_own_scope_can_view_order_when_manager_differs(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = User::factory()->create();
        $manager = User::factory()->create();

        $order = new Order([
            'manager_id' => $manager->id,
            'order_owner_id' => $owner->id,
        ]);

        $this->assertTrue(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $owner->id));
    }

    public function test_unrelated_manager_with_own_scope_cannot_own_order_record(): void
    {
        $managerA = User::factory()->create();
        $managerB = User::factory()->create();

        $order = new Order([
            'manager_id' => $managerA->id,
        ]);

        $this->assertFalse(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $managerB->id));
    }
}
