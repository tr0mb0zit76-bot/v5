<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpAccessGateOrderScopeTest extends TestCase
{
    public function test_find_accessible_order_allows_order_owner_with_own_scope(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = $this->createOrdersUser('deal_owner');
        $dispatcher = User::factory()->create();

        $order = Order::factory()->create([
            'manager_id' => $dispatcher->id,
            'order_owner_id' => $owner->id,
        ]);

        $found = app(McpAccessGate::class)->findAccessibleOrder($owner, (int) $order->id);

        $this->assertSame((int) $order->id, (int) $found->id);
    }

    public function test_find_accessible_order_rejects_foreign_order_with_own_scope(): void
    {
        $owner = $this->createOrdersUser('manager_a');
        $intruder = $this->createOrdersUser('manager_b');

        $order = Order::factory()->create([
            'manager_id' => $owner->id,
        ]);

        $this->expectException(AuthenticationException::class);

        app(McpAccessGate::class)->findAccessibleOrder($intruder, (int) $order->id);
    }

    public function test_ensure_can_edit_order_allows_department_colleague_order(): void
    {
        if (! Schema::hasTable('department_user') || ! Schema::hasTable('departments')) {
            $this->markTestSkipped('department tables are unavailable.');
        }

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'MCP dept',
            'permissions' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'department'],
        ]);

        $departmentId = \Illuminate\Support\Facades\DB::table('departments')->insertGetId([
            'name' => 'MCP dept '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $colleague = User::factory()->create(['role_id' => $role->id]);
        $viewer = User::factory()->create(['role_id' => $role->id]);

        foreach ([$colleague, $viewer] as $member) {
            \Illuminate\Support\Facades\DB::table('department_user')->insert([
                'department_id' => $departmentId,
                'user_id' => $member->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $order = Order::factory()->create([
            'manager_id' => $colleague->id,
        ]);

        app(McpAccessGate::class)->ensureCanEditOrder($viewer, $order);

        $this->assertTrue(true);
    }

    private function createOrdersUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
        ], [
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        $role->update([
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
