<?php

namespace Tests\Unit;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesBookArticleStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['sales_book_articles']);

        Schema::create('sales_book_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('markdown_content')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 24)->default(SalesBookArticleStatus::Published->value);
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $this->schemaDropMany(['sales_book_articles']);

        parent::tearDown();
    }

    public function test_published_scope_excludes_drafts(): void
    {
        DB::table('sales_book_articles')->insert([
            [
                'title' => 'Опубликованная',
                'status' => SalesBookArticleStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Черновик',
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
            'tags' => ['CMR', 'документы'],
        ]);

        $this->assertSame(['CMR', 'документы'], $article->refresh()->tags);
    }
}
