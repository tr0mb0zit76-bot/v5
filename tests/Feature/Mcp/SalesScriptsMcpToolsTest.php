<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetSalesScriptGraphTool;
use App\Mcp\Tools\ListSalesScriptsTool;
use App\Mcp\Tools\ValidateSalesScriptGraphTool;
use App\Models\Role;
use App\Models\SalesScript;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Tests\TestCase;

class SalesScriptsMcpToolsTest extends TestCase
{
    public function test_sales_script_read_tools_expose_and_validate_graph(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);
        $user = $this->analyticsUser();
        $script = SalesScript::query()
            ->where('title', 'Первичный запрос ставки (экспедиция)')
            ->firstOrFail();

        CrmServer::actingAs($user)
            ->tool(ListSalesScriptsTool::class, [])
            ->assertOk()
            ->assertSee('Первичный запрос ставки')
            ->assertSee('node_templates_count');

        CrmServer::actingAs($user)
            ->tool(GetSalesScriptGraphTool::class, ['script_id' => $script->id])
            ->assertOk()
            ->assertSee('customer_label')
            ->assertSee('conversation_effect')
            ->assertSee('next_move_preview');

        CrmServer::actingAs($user)
            ->tool(ValidateSalesScriptGraphTool::class, ['script_id' => $script->id])
            ->assertOk()
            ->assertSee('"valid":true')
            ->assertSee('automatic_effect');
    }

    private function analyticsUser(): User
    {
        $role = Role::query()->create([
            'name' => 'sales_scripts_mcp_'.uniqid(),
            'display_name' => 'Sales scripts MCP',
            'permissions' => [],
            'visibility_areas' => ['sales_assistant_trainer_analytics', 'scripts'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
