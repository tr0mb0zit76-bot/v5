<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\AddOrderNoteTool;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\McpTokenAbilities;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpHttpAuthenticationTest extends TestCase
{
    public function test_web_session_without_bearer_token_is_denied(): void
    {
        $user = $this->makeUserWithOrdersAccess();

        $this->actingAs($user)
            ->postJson('/mcp/crm', $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_missing_bearer_token_is_denied_with_json_response(): void
    {
        $response = $this->postJson('/mcp/crm', $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate')
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertStringStartsWith(
            'Bearer',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_invalid_bearer_token_is_denied(): void
    {
        $this->postJson('/mcp/crm', $this->initializePayload(), $this->mcpHeaders('invalid-token'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_invalid_bearer_attempts_are_rate_limited_before_authentication(): void
    {
        RateLimiter::clear('mcp-ip-127.0.0.1');

        foreach (range(1, 30) as $attempt) {
            $this->postJson(
                '/mcp/crm',
                $this->initializePayload(),
                $this->mcpHeaders('invalid-token-'.$attempt),
            )->assertUnauthorized();
        }

        $this->postJson('/mcp/crm', $this->initializePayload(), $this->mcpHeaders('invalid-token-final'))
            ->assertTooManyRequests();
    }

    public function test_valid_read_token_can_initialize_and_call_read_tool(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = $this->makeUserWithOrdersAccess();
        $token = $user->createToken('mcp-http-test', [McpTokenAbilities::READ]);

        $this->postJson('/mcp/crm', $this->initializePayload(), $this->mcpHeaders($token->plainTextToken))
            ->assertOk();
        $this->assertNotNull($token->accessToken->fresh()->last_used_at);

        $this->postJson('/mcp/crm', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_user_context',
                'arguments' => (object) [],
            ],
        ], $this->mcpHeaders($token->plainTextToken))
            ->assertOk()
            ->assertSee('visibility_areas');
    }

    public function test_read_only_token_still_rejects_write_tool(): void
    {
        if (! Schema::hasTable('personal_access_tokens') || ! Schema::hasTable('orders')) {
            $this->markTestSkipped('Required tables are unavailable.');
        }

        $user = $this->makeUserWithOrdersAccess();
        $order = Order::factory()->create(['manager_id' => $user->id]);
        $token = $user->createToken('mcp-http-test', [McpTokenAbilities::READ]);
        $user->withAccessToken($token->accessToken);

        CrmServer::actingAs($user)
            ->tool(AddOrderNoteTool::class, [
                'order_id' => $order->id,
                'body' => 'Read-only MCP token',
            ])
            ->assertHasErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => (object) [],
                'clientInfo' => [
                    'name' => 'phpunit',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mcpHeaders(?string $token = null): array
    {
        return array_filter([
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => $token === null ? null : 'Bearer '.$token,
        ]);
    }

    private function makeUserWithOrdersAccess(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'mcp-http-auth-test'],
            [
                'display_name' => 'MCP HTTP auth test',
                'permissions' => [],
                'visibility_areas' => ['orders'],
                'visibility_scopes' => ['orders' => 'own'],
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
