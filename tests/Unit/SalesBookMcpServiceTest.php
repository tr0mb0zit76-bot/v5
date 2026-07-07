<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use App\Services\Mcp\SalesBookMcpService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SalesBookMcpServiceTest extends TestCase
{
    private SalesBookMcpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalesBookMcpService::class);
    }

    #[Test]
    public function search_finds_articles_by_markdown_content(): void
    {
        $user = $this->adminUser();

        SalesBookArticle::query()->create([
            'title' => 'Общий раздел',
            'markdown_content' => 'Краткое описание.',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Скрытый заголовок',
            'markdown_content' => 'Подробная инструкция по клиентской базе и экспорту.',
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $result = $this->service->search($user, 'клиентской базе', 10);

        $this->assertCount(1, $result['articles']);
        $this->assertSame($article->id, $result['articles'][0]['id']);
        $this->assertSame('content', $result['articles'][0]['matched_in']);
        $this->assertNotNull($result['articles'][0]['excerpt']);
    }

    #[Test]
    public function search_filters_articles_by_properties(): void
    {
        $user = $this->adminUser();

        SalesBookArticle::query()->create([
            'title' => 'Материал для руководителя',
            'markdown_content' => 'Планерка и контроль.',
            'parent_id' => null,
            'sort_order' => 0,
            'properties' => [
                'audience_role' => 'supervisor',
                'sales_stage' => 'negotiation',
            ],
        ]);

        $managerArticle = SalesBookArticle::query()->create([
            'title' => 'Материал для менеджера',
            'markdown_content' => 'Как подготовить КП.',
            'parent_id' => null,
            'sort_order' => 1,
            'properties' => [
                'audience_role' => 'manager',
                'sales_stage' => 'offer',
            ],
        ]);

        $result = $this->service->search($user, '', 10, [
            'audience_role' => 'manager',
            'unknown' => 'ignored',
        ]);

        $this->assertCount(1, $result['articles']);
        $this->assertSame($managerArticle->id, $result['articles'][0]['id']);
        $this->assertSame('manager', $result['articles'][0]['properties']['audience_role']);
        $this->assertSame('Менеджер', $result['articles'][0]['property_labels']['audience_role']);
    }

    #[Test]
    public function search_can_use_system_view_filters(): void
    {
        $user = $this->adminUser();

        SalesBookArticle::query()->create([
            'title' => 'Для логиста',
            'markdown_content' => 'Маршрут.',
            'parent_id' => null,
            'sort_order' => 0,
            'properties' => [
                'audience_role' => 'logist',
            ],
        ]);

        $managerArticle = SalesBookArticle::query()->create([
            'title' => 'Для менеджера',
            'markdown_content' => 'Возражения клиента.',
            'parent_id' => null,
            'sort_order' => 1,
            'properties' => [
                'audience_role' => 'manager',
            ],
        ]);

        $result = $this->service->search($user, '', 10, [], 'manager-materials');

        $this->assertCount(1, $result['articles']);
        $this->assertSame($managerArticle->id, $result['articles'][0]['id']);
    }

    #[Test]
    public function get_returns_reader_markdown_with_breadcrumb(): void
    {
        $user = $this->adminUser();

        $parent = SalesBookArticle::query()->create([
            'title' => 'Экспресс-введение',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'День 4: Клиентская база',
            'markdown_content' => "## Шаги\n\n1. Откройте раздел контрагентов.",
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $result = $this->service->get($user, $child->id);

        $this->assertSame($child->id, $result['article']['id']);
        $this->assertSame('День 4: Клиентская база', $result['article']['title']);
        $this->assertSame($parent->id, $result['article']['parent_id']);
        $this->assertSame('Экспресс-введение', $result['article']['parent_title']);
        $this->assertSame([
            ['id' => $parent->id, 'title' => 'Экспресс-введение'],
        ], $result['article']['breadcrumb']);
        $this->assertStringContainsString('контрагентов', $result['article']['markdown_content']);
        $this->assertFalse($result['article']['content_truncated']);
    }

    #[Test]
    public function get_can_return_blocks_snapshot(): void
    {
        $user = $this->adminUser();

        $article = SalesBookArticle::query()->create([
            'title' => 'Работа с возражениями',
            'markdown_content' => "## Цена\n\n- Уточнить маршрут\n- Показать риски",
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $result = $this->service->get($user, $article->id, null, 'blocks');

        $this->assertArrayNotHasKey('markdown_content', $result['article']);
        $this->assertSame('sales_book_blocks_v1', $result['article']['blocks_snapshot']['schema']);
        $this->assertSame(['heading', 'list'], array_column($result['article']['blocks_snapshot']['blocks'], 'type'));
        $this->assertSame('Цена', $result['article']['blocks_snapshot']['blocks'][0]['text']);
    }

    #[Test]
    public function get_truncates_long_content(): void
    {
        $user = $this->adminUser();

        $article = SalesBookArticle::query()->create([
            'title' => 'Длинная статья',
            'markdown_content' => str_repeat('А', 500),
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $result = $this->service->get($user, $article->id, 100);

        $this->assertTrue($result['article']['content_truncated']);
        $this->assertLessThanOrEqual(103, mb_strlen($result['article']['markdown_content']));
    }

    #[Test]
    public function get_throws_when_article_missing(): void
    {
        $user = $this->adminUser();

        $this->expectException(ModelNotFoundException::class);

        $this->service->get($user, 99999);
    }

    #[Test]
    public function read_is_denied_without_sales_book_access(): void
    {
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Нет доступа к чтению Книги продаж.');

        $this->service->search($user, 'test', 5);
    }

    private function adminUser(): User
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => [],
            'visibility_areas' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
