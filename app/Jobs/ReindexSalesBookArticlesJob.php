<?php

namespace App\Jobs;

use App\Models\SalesBookArticle;
use App\Services\SalesBookArticleTreeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;

class ReindexSalesBookArticlesJob implements ShouldQueue
{
    use Queueable;

    public function handle(SalesBookArticleTreeService $treeService): void
    {
        if (! Schema::hasTable('sales_book_articles')) {
            return;
        }

        $parentIds = SalesBookArticle::query()
            ->select('parent_id')
            ->distinct()
            ->pluck('parent_id');

        foreach ($parentIds as $parentId) {
            $treeService->reindexSiblings(is_numeric($parentId) ? (int) $parentId : null);
        }
    }
}
