<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ReindexSalesBookArticlesJob;
use App\Models\SalesBookArticle;
use App\Services\SalesBookArticleTreeService;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReindexSalesBookArticlesJobTest extends TestCase
{
    #[Test]
    public function it_reindexes_all_parent_groups(): void
    {
        if (! Schema::hasTable('sales_book_articles')) {
            $this->markTestSkipped('sales_book_articles table unavailable.');
        }

        SalesBookArticle::query()->create([
            'title' => 'Root A',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 1,
        ]);
        SalesBookArticle::query()->create([
            'title' => 'Root B',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 2,
        ]);

        $tree = Mockery::mock(SalesBookArticleTreeService::class);
        $tree->shouldReceive('reindexSiblings')->once()->with(null);
        $this->app->instance(SalesBookArticleTreeService::class, $tree);

        ReindexSalesBookArticlesJob::dispatchSync();
    }
}
