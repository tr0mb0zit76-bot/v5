<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateProposalHtmlTemplateTool;
use App\Mcp\Tools\GetProposalHtmlTemplateTool;
use App\Mcp\Tools\ListProposalHtmlTemplatesTool;
use App\Mcp\Tools\UpdateProposalHtmlTemplateTool;
use App\Models\ProposalHtmlTemplate;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ProposalHtmlTemplatesMcpToolsTest extends TestCase
{
    public function test_list_returns_stock_assets_for_settings_user(): void
    {
        $user = $this->settingsSystemUser();

        ProposalHtmlTemplate::factory()->create([
            'name' => 'Параллельный импорт',
            'slug' => 'parallel-import',
            'owner_user_id' => $user->id,
        ]);

        $response = CrmServer::actingAs($user)->tool(ListProposalHtmlTemplatesTool::class, []);

        $response
            ->assertOk()
            ->assertSee('parallel-import')
            ->assertSee('stock_assets')
            ->assertSee('route.svg');
    }

    public function test_create_cold_template_with_stock_asset(): void
    {
        $user = $this->settingsSystemUser();
        $slug = 'mcp-cold-test-'.uniqid();

        $response = CrmServer::actingAs($user)->tool(CreateProposalHtmlTemplateTool::class, [
            'mode' => 'cold',
            'name' => 'Тестовое холодное КП',
            'slug' => $slug,
            'title' => 'Логистика под ваш маршрут',
            'intro' => 'Пишу коротко про перевозку.',
            'points' => ['ставка без сюрпризов', 'статус по этапам'],
            'cta' => 'Пришлите маршрут и вес.',
            'stock_asset' => 'customs.svg',
        ]);

        $response
            ->assertOk()
            ->assertSee('"mode":"cold"', false)
            ->assertSee('Тестовое холодное КП')
            ->assertSee('customs.svg');

        $html = (string) ProposalHtmlTemplate::query()->where('slug', $slug)->value('html_body');
        $this->assertStringContainsString('Логистика под ваш маршрут', $html);
        $this->assertStringContainsString('/assets/proposal-emails/customs.svg', $html);
    }

    public function test_clone_and_update_with_text_replacements(): void
    {
        $user = $this->settingsSystemUser();
        $slug = 'mcp-clone-chem-'.uniqid();

        ProposalHtmlTemplate::factory()->create([
            'name' => 'Параллельный импорт',
            'slug' => 'parallel-import',
            'html_body' => '<html><body><h1>Параллельный импорт</h1><img src="/assets/proposal-emails/hero.png" alt=""><p><a href="mailto:{manager.email}">{manager.email}</a></p></body></html>',
            'owner_user_id' => $user->id,
        ]);

        CrmServer::actingAs($user)->tool(CreateProposalHtmlTemplateTool::class, [
            'mode' => 'clone',
            'name' => 'КП под химию',
            'slug' => $slug,
            'base_slug' => 'parallel-import',
            'text_replacements' => [
                'Параллельный импорт' => 'Химические грузы',
            ],
        ])
            ->assertOk()
            ->assertSee('"mode":"clone"', false)
            ->assertSee('Химические грузы');

        $this->assertDatabaseHas('proposal_html_templates', [
            'slug' => $slug,
            'name' => 'КП под химию',
        ]);

        CrmServer::actingAs($user)->tool(GetProposalHtmlTemplateTool::class, [
            'slug' => $slug,
            'include_html' => true,
        ])
            ->assertOk()
            ->assertSee('Химические грузы')
            ->assertSee('mailto:{manager.email}');

        CrmServer::actingAs($user)->tool(UpdateProposalHtmlTemplateTool::class, [
            'slug' => $slug,
            'text_replacements' => [
                'Химические грузы' => 'Опасные грузы ADR',
            ],
        ])
            ->assertOk()
            ->assertSee('Опасные грузы ADR');
    }

    public function test_denied_without_settings_system(): void
    {
        $role = Role::query()->create([
            'name' => 'mcp_orders_'.uniqid(),
            'display_name' => 'MCP Orders',
            'permissions' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => [],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response = CrmServer::actingAs($user)->tool(ListProposalHtmlTemplatesTool::class, []);

        $response->assertHasErrors();
    }

    private function settingsSystemUser(): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_settings_'.uniqid(),
            'display_name' => 'MCP Settings',
            'permissions' => [],
            'visibility_areas' => ['settings_system'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
