<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetContractorTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\SearchContractorsTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class CrmMcpToolsTest extends TestCase
{
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

    public function test_search_contractors_finds_by_inn(): void
    {
        $user = $this->makeUserWithAreas(['contractors'], ['contractors' => 'all']);

        Contractor::query()->create([
            'type' => 'customer',
            'name' => 'MCP Test Customer',
            'inn' => '7707083893',
            'is_active' => true,
        ]);

        $response = CrmServer::actingAs($user)->tool(SearchContractorsTool::class, [
            'query' => '7707083893',
            'limit' => 5,
        ]);

        $response
            ->assertOk()
            ->assertSee('MCP Test Customer');

        $contractor = Contractor::query()->where('inn', '7707083893')->first();
        $this->assertNotNull($contractor);

        $detail = CrmServer::actingAs($user)->tool(GetContractorTool::class, [
            'contractor_id' => $contractor->id,
        ]);

        $detail->assertOk()->assertSee('7707083893');
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUserWithAreas(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_test_'.uniqid(),
            'display_name' => 'MCP Test',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserWithOrdersAccess(array $overrides = []): User
    {
        $user = $this->makeUserWithAreas(
            ['orders', 'dashboard'],
            ['orders' => 'own'],
        );

        if ($overrides !== []) {
            $user->fill($overrides);
            $user->save();
        }

        return $user->fresh() ?? $user;
    }
}
