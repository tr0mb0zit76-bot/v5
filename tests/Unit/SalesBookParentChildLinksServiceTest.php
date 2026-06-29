<?php

namespace Tests\Unit;

use App\Models\SalesBookArticle;
use App\Services\SalesBookParentChildLinksService;
use Tests\TestCase;

class SalesBookParentChildLinksServiceTest extends TestCase
{
    public function test_sync_parent_appends_markdown_links_for_direct_children(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => "Краткое введение.\n",
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Водители',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookParentChildLinksService::class);
        $service->syncParent($parent);

        $parent->refresh();

        $this->assertStringContainsString('Краткое введение.', $parent->markdown_content);
        $this->assertStringContainsString(SalesBookParentChildLinksService::START_MARKER, $parent->markdown_content);
        $this->assertStringContainsString(
            sprintf('- [Водители](/sales-assistant/book?article_id=%d)', $child->id),
            $parent->markdown_content,
        );
    }

    public function test_sync_parent_removes_links_block_when_last_child_deleted(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => "Intro\n\n".SalesBookParentChildLinksService::START_MARKER."\n- [Child](/sales-assistant/book?article_id=99)\n".SalesBookParentChildLinksService::END_MARKER,
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookParentChildLinksService::class);
        $service->syncParent($parent);

        $parent->refresh();

        $this->assertSame('Intro', trim($parent->markdown_content));
        $this->assertStringNotContainsString(SalesBookParentChildLinksService::START_MARKER, $parent->markdown_content);
    }

    public function test_merge_child_links_into_content_replaces_existing_block(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $firstChild = SalesBookArticle::query()->create([
            'title' => 'First',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $secondChild = SalesBookArticle::query()->create([
            'title' => 'Second',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        $service = app(SalesBookParentChildLinksService::class);
        $service->syncParent($parent);
        $parent->refresh();

        $merged = $service->mergeChildLinksIntoContent($parent->markdown_content, $parent->id);

        $this->assertStringContainsString(sprintf('- [First](/sales-assistant/book?article_id=%d)', $firstChild->id), $merged);
        $this->assertStringContainsString(sprintf('- [Second](/sales-assistant/book?article_id=%d)', $secondChild->id), $merged);
        $this->assertSame(1, substr_count($merged, SalesBookParentChildLinksService::START_MARKER));
    }

    public function test_sync_parent_puts_links_at_start_when_parent_body_is_empty(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Раздел',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Инструкция',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookParentChildLinksService::class);
        $service->syncParent($parent);

        $parent->refresh();

        $this->assertStringStartsWith(SalesBookParentChildLinksService::START_MARKER, trim($parent->markdown_content));
        $this->assertStringContainsString(
            sprintf('- [Инструкция](/sales-assistant/book?article_id=%d)', $child->id),
            $parent->markdown_content,
        );
    }

    public function test_ensure_child_links_synced_backfills_missing_block_on_first_view(): void
    {
        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent',
            'markdown_content' => 'Intro only',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        SalesBookArticle::query()->create([
            'title' => 'Child',
            'markdown_content' => '',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        $service = app(SalesBookParentChildLinksService::class);
        $service->ensureChildLinksSynced($parent);

        $parent->refresh();

        $this->assertStringContainsString(SalesBookParentChildLinksService::START_MARKER, $parent->markdown_content);
    }
}
