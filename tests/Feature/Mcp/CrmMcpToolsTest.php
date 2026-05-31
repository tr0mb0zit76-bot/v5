<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMcpToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_context_returns_visibility_areas(): void
    {
        $user = $this->makeUserWithOrdersAccess();

        $response = CrmServer::actingAs($user)->tool(GetUserContextTool::class, []);

        $response
            ->assertOk()
            ->assertSee('visibility_areas')
            ->assertSee('orders');
    }

    public function test_search_orders_respects_manager_scope(): void
    {
        $managerA = $this->makeUserWithOrdersAccess(['name' => 'Manager A']);
        $managerB = $this->makeUserWithOrdersAccess(['name' => 'Manager B']);

        $visible = Order::factory()->create([
            'order_number' => 'MCP-VISIBLE-001',
            'manager_id' => $managerA->id,
        ]);

        Order::factory()->create([
            'order_number' => 'MCP-HIDDEN-002',
            'manager_id' => $managerB->id,
        ]);

        $response = CrmServer::actingAs($managerA)->tool(SearchOrdersTool::class, [
            'query' => 'MCP-VISIBLE',
            'limit' => 10,
        ]);

        $response
            ->assertOk()
            ->assertSee('MCP-VISIBLE-001')
            ->assertDontSee('MCP-HIDDEN-002');
    }

    public function test_get_order_returns_card_for_accessible_order(): void
    {
        $user = $this->makeUserWithOrdersAccess();

        $order = Order::factory()->create([
            'order_number' => 'MCP-DETAIL-100',
            'manager_id' => $user->id,
        ]);

        $response = CrmServer::actingAs($user)->tool(GetOrderTool::class, [
            'order_id' => $order->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('MCP-DETAIL-100');
    }

    public function test_get_order_denied_for_other_manager_order(): void
    {
        $user = $this->makeUserWithOrdersAccess();
        $other = $this->makeUserWithOrdersAccess(['email' => 'other-mcp@example.com']);

        $order = Order::factory()->create([
            'manager_id' => $other->id,
        ]);

        $response = CrmServer::actingAs($user)->tool(GetOrderTool::class, [
            'order_id' => $order->id,
        ]);

        $response->assertHasErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserWithOrdersAccess(array $overrides = []): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_test_'.uniqid(),
            'display_name' => 'MCP Test',
            'permissions' => [],
            'visibility_areas' => ['orders', 'dashboard'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
    }
}
