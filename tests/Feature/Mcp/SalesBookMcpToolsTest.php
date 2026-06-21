<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\UpsertSalesBookArticleTool;
use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBookMcpToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_sales_book_articles_finds_pages_by_title(): void
    {
        $user = $this->makeUserWithSalesBookRead();

        SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '# Руководство',
            'sort_order' => 0,
        ]);

        $response = CrmServer::actingAs($user)->tool(SearchSalesBookArticlesTool::class, [
            'query' => 'Руководство',
            'limit' => 10,
        ]);

        $response
            ->assertOk()
            ->assertSee('Руководство по CRM');
    }

    public function test_upsert_sales_book_article_creates_and_updates_child_page(): void
    {
        $user = $this->makeUserWithSalesBookWrite();

        $parent = SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '# Руководство',
            'sort_order' => 0,
        ]);

        $createResponse = CrmServer::actingAs($user)->tool(UpsertSalesBookArticleTool::class, [
            'parent_title' => 'Руководство по CRM',
            'title' => 'Документы',
            'markdown_content' => "# Документы\n\nПервая версия.",
        ]);

        $createResponse
            ->assertOk()
            ->assertSee('"action":"created"', false)
            ->assertSee('Документы');

        $article = SalesBookArticle::query()
            ->where('parent_id', $parent->id)
            ->where('title', 'Документы')
            ->first();

        $this->assertNotNull($article);
        $this->assertStringContainsString('Первая версия', (string) $article->markdown_content);

        $updateResponse = CrmServer::actingAs($user)->tool(UpsertSalesBookArticleTool::class, [
            'parent_title' => 'Руководство по CRM',
            'title' => 'Документы',
            'markdown_content' => "# Документы\n\nВторая версия.",
        ]);

        $updateResponse
            ->assertOk()
            ->assertSee('"action":"updated"', false)
            ->assertSee('Вторая версия');

        $this->assertSame(1, SalesBookArticle::query()->where('parent_id', $parent->id)->where('title', 'Документы')->count());
    }

    public function test_upsert_denied_without_sales_book_write(): void
    {
        $user = $this->makeUserWithSalesBookRead();

        SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '# Руководство',
            'sort_order' => 0,
        ]);

        $response = CrmServer::actingAs($user)->tool(UpsertSalesBookArticleTool::class, [
            'parent_title' => 'Руководство по CRM',
            'title' => 'Документы',
            'markdown_content' => '# Документы',
        ]);

        $response->assertHasErrors();
    }

    private function makeUserWithSalesBookRead(): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_book_read_'.uniqid(),
            'display_name' => 'MCP Book Read',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_book'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeUserWithSalesBookWrite(): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_book_write_'.uniqid(),
            'display_name' => 'MCP Book Write',
            'permissions' => ['sales_book_write'],
            'visibility_areas' => ['sales_assistant_book'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
