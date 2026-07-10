<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderViewAuthorizationTest extends TestCase
{
    public function test_manager_with_own_scope_cannot_open_foreign_order_wizard(): void
    {
        if (! Schema::hasTable('orders')) {
            $this->markTestSkipped('orders table is unavailable.');
        }

        $owner = $this->createOrdersManager('manager_a');
        $intruder = $this->createOrdersManager('manager_b');

        $order = Order::factory()->create([
            'manager_id' => $owner->id,
        ]);

        $this->actingAs($intruder)
            ->get(route('orders.edit', $order))
            ->assertForbidden();
    }

    public function test_order_owner_with_own_scope_can_open_order_wizard(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = $this->createOrdersManager('deal_owner');
        $dispatcherManager = $this->createOrdersManager('dispatcher_manager');

        $order = Order::factory()->create([
            'manager_id' => $dispatcherManager->id,
            'order_owner_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('orders.edit', $order))
            ->assertOk();
    }

    private function createOrdersManager(string $roleName): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
        ], [
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders', 'dashboard'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        $role->update([
            'visibility_areas' => ['orders', 'dashboard'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
