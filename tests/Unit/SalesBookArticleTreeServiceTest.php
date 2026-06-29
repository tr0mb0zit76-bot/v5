<?php

namespace Tests\Unit;

use App\Models\SalesBookArticle;
use App\Services\SalesBookArticleTreeService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesBookArticleTreeServiceTest extends TestCase
{
    public function test_build_tree_nests_articles_by_parent_id(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Child',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookArticleTreeService::class);
        $tree = $service->buildTree(SalesBookArticle::query()->orderBy('id')->get())->values()->all();

        $this->assertCount(1, $tree);
        $this->assertSame('Parent', $tree[0]['title']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame($child->id, $tree[0]['children'][0]['id']);
    }

    public function test_move_article_reparents_and_reindexes_siblings(): void
    {
        $rootA = SalesBookArticle::query()->create([
            'title' => 'Root A',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $rootB = SalesBookArticle::query()->create([
            'title' => 'Root B',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $service = app(SalesBookArticleTreeService::class);
        $service->moveArticle($rootB, $rootA->id, 0);

        $rootB->refresh();
        $this->assertSame($rootA->id, $rootB->parent_id);
        $this->assertSame(0, $rootB->sort_order);
    }

    public function test_build_tree_nests_sibling_branches_under_same_parent(): void
    {
        $crm = SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $orders = SalesBookArticle::query()->create([
            'title' => 'Работа с заказами',
            'markdown_content' => '',
            'parent_id' => $crm->id,
            'sort_order' => 1,
        ]);

        $drivers = SalesBookArticle::query()->create([
            'title' => 'Водители',
            'markdown_content' => '',
            'parent_id' => $crm->id,
            'sort_order' => 0,
        ]);

        $master = SalesBookArticle::query()->create([
            'title' => 'Мастер заказов',
            'markdown_content' => '',
            'parent_id' => $orders->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookArticleTreeService::class);
        $tree = $service->buildTree(SalesBookArticle::query()->orderBy('id')->get())->values()->all();

        $this->assertCount(1, $tree);
        $this->assertSame($crm->id, $tree[0]['id']);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertSame($drivers->id, $tree[0]['children'][0]['id']);
        $this->assertSame($orders->id, $tree[0]['children'][1]['id']);
        $this->assertCount(1, $tree[0]['children'][1]['children']);
        $this->assertSame($master->id, $tree[0]['children'][1]['children'][0]['id']);
    }

    public function test_direct_children_returns_sorted_child_pages(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $childB = SalesBookArticle::query()->create([
            'title' => 'Child B',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        $childA = SalesBookArticle::query()->create([
            'title' => 'Child A',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookArticleTreeService::class);
        $children = $service->directChildren(SalesBookArticle::query()->get(), $parent->id);

        $this->assertSame([
            ['id' => $childA->id, 'title' => 'Child A'],
            ['id' => $childB->id, 'title' => 'Child B'],
        ], $children);
    }

    public function test_parent_matches_treats_numeric_strings_as_equal(): void
    {
        $service = app(SalesBookArticleTreeService::class);

        $this->assertTrue($service->parentMatches('5', 5));
        $this->assertTrue($service->parentMatches(5, 5));
        $this->assertTrue($service->parentMatches(null, null));
        $this->assertTrue($service->parentMatches('', null));
        $this->assertTrue($service->parentMatches(0, null));
        $this->assertFalse($service->parentMatches(null, 5));
        $this->assertFalse($service->parentMatches('6', 5));
    }

    public function test_build_tree_keeps_multiple_root_sections_separate(): void
    {
        $crm = SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        SalesBookArticle::query()->create([
            'title' => 'Работа с заказами',
            'markdown_content' => '',
            'parent_id' => $crm->id,
            'sort_order' => 0,
        ]);

        $knowledgeBase = SalesBookArticle::query()->create([
            'title' => 'База знаний',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $service = app(SalesBookArticleTreeService::class);
        $tree = $service->buildTree(SalesBookArticle::query()->orderBy('id')->get())->values()->all();

        $this->assertCount(2, $tree);
        $this->assertSame($crm->id, $tree[0]['id']);
        $this->assertSame($knowledgeBase->id, $tree[1]['id']);
        $this->assertNull($tree[1]['parent_id']);
        $this->assertSame([], $tree[1]['children']);
    }

    public function test_move_article_rejects_circular_parent(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Child',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookArticleTreeService::class);

        $this->expectException(ValidationException::class);
        $service->moveArticle($parent, $child->id, 0);
    }
}
