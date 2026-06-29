<?php

namespace Tests\Unit;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesBookArticleStatusTest extends TestCase
{
    public function test_published_scope_excludes_drafts(): void
    {
        DB::table('sales_book_articles')->insert([
            [
                'title' => 'Опубликованная',
                'markdown_content' => '',
                'status' => SalesBookArticleStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Черновик',
                'markdown_content' => '',
                'status' => SalesBookArticleStatus::Draft->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $titles = SalesBookArticle::query()->published()->pluck('title')->all();

        $this->assertSame(['Опубликованная'], $titles);
    }

    public function test_tags_are_cast_to_array(): void
    {
        $article = SalesBookArticle::query()->create([
            'title' => 'Документы',
            'markdown_content' => '',
            'tags' => ['CMR', 'документы'],
        ]);

        $this->assertSame(['CMR', 'документы'], $article->refresh()->tags);
    }
}
