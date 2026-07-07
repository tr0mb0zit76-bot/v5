<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetSalesBookArticleTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\UpsertSalesBookArticleTool;
use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use Tests\TestCase;

class SalesBookMcpToolsTest extends TestCase
{
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
            ->assertSee('"action":"updated"', false);

        $article->refresh();

        $this->assertStringContainsString('Вторая версия', (string) $article->markdown_content);
        $this->assertSame(1, SalesBookArticle::query()->where('parent_id', $parent->id)->where('title', 'Документы')->count());
    }

    public function test_upsert_creates_parent_when_flag_is_set(): void
    {
        $user = $this->makeUserWithSalesBookWrite();

        $response = CrmServer::actingAs($user)->tool(UpsertSalesBookArticleTool::class, [
            'parent_title' => 'Руководство по CRM',
            'title' => 'Traklo для менеджера',
            'markdown_content' => "# Traklo\n\nИнструкция.",
            'create_parent_if_missing' => true,
        ]);

        $response
            ->assertOk()
            ->assertSee('"action":"created"', false)
            ->assertSee('Traklo для менеджера');

        $parent = SalesBookArticle::query()
            ->whereNull('parent_id')
            ->where('title', 'Руководство по CRM')
            ->first();

        $this->assertNotNull($parent);

        $child = SalesBookArticle::query()
            ->where('parent_id', $parent->id)
            ->where('title', 'Traklo для менеджера')
            ->first();

        $this->assertNotNull($child);
    }

    public function test_get_sales_book_article_can_return_blocks_format(): void
    {
        $user = $this->makeUserWithSalesBookRead();

        $article = SalesBookArticle::query()->create([
            'title' => 'Скрипт КП',
            'markdown_content' => "## Подготовка\n\n- собрать вводные",
            'sort_order' => 0,
        ]);

        $response = CrmServer::actingAs($user)->tool(GetSalesBookArticleTool::class, [
            'article_id' => $article->id,
            'format' => 'blocks',
        ]);

        $response
            ->assertOk()
            ->assertSee('"blocks_snapshot"', false)
            ->assertSee('"type":"heading"', false)
            ->assertDontSee('"markdown_content"', false);
    }

    public function test_upsert_sales_book_article_accepts_blocks(): void
    {
        $user = $this->makeUserWithSalesBookWrite();

        $parent = SalesBookArticle::query()->create([
            'title' => 'Руководство по продажам',
            'markdown_content' => '# Руководство',
            'sort_order' => 0,
        ]);

        $response = CrmServer::actingAs($user)->tool(UpsertSalesBookArticleTool::class, [
            'parent_title' => 'Руководство по продажам',
            'title' => 'Возражение по цене',
            'blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Цена'],
                ['type' => 'paragraph', 'text' => 'Показываем риски и сроки.'],
                ['type' => 'list', 'items' => [
                    ['text' => 'сравнить маршрут'],
                    ['text' => 'зафиксировать дедлайн'],
                ]],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('"action":"created"', false);

        $article = SalesBookArticle::query()
            ->where('parent_id', $parent->id)
            ->where('title', 'Возражение по цене')
            ->first();

        $this->assertNotNull($article);
        $this->assertStringContainsString('## Цена', (string) $article->markdown_content);
        $this->assertSame('sales_book_blocks_v1', $article->blocks_snapshot['schema']);
        $this->assertSame(['heading', 'paragraph', 'list'], array_column($article->blocks_snapshot['blocks'], 'type'));
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
