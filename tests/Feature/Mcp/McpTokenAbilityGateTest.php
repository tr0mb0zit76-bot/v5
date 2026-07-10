<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\AddOrderNoteTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\McpTokenAbilities;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpTokenAbilityGateTest extends TestCase
{
    public function test_read_only_token_can_call_read_tool(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = $this->makeUserWithOrdersAccess();
        $token = $user->createToken('test', [McpTokenAbilities::READ]);
        $user->withAccessToken($token->accessToken);

        $response = CrmServer::actingAs($user)->tool(GetUserContextTool::class, []);

        $response->assertOk()->assertSee('visibility_areas');
    }

    public function test_read_only_token_rejects_write_tool(): void
    {
        if (! Schema::hasTable('personal_access_tokens') || ! Schema::hasTable('orders')) {
            $this->markTestSkipped('Required tables are unavailable.');
        }

        $user = $this->makeUserWithOrdersAccess();
        $order = Order::factory()->create(['manager_id' => $user->id]);

        $token = $user->createToken('test', [McpTokenAbilities::READ]);
        $user->withAccessToken($token->accessToken);

        $response = CrmServer::actingAs($user)->tool(AddOrderNoteTool::class, [
            'order_id' => $order->id,
            'body' => 'Тест MCP read-only',
        ]);

        $response->assertHasErrors();
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUserWithOrdersAccess(array $overrides = [], array $areas = ['orders'], array $scopes = ['orders' => 'own']): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'mcp-test-orders-'.md5(json_encode([$areas, $scopes]))],
            [
                'display_name' => 'MCP test',
                'permissions' => [],
                'visibility_areas' => $areas,
                'visibility_scopes' => $scopes,
            ],
        );

        $user = User::factory()->create(array_merge(['role_id' => $role->id, 'is_active' => true], $overrides));

        return $user;
    }
}
