<?php

namespace Tests\Unit;

use App\Models\SalesBookArticle;
use App\Models\SalesBookQuizAttempt;
use App\Models\User;
use App\Services\SalesBook\SalesBookQuizInsightsService;
use Tests\TestCase;

class SalesBookQuizInsightsServiceTest extends TestCase
{
    public function test_it_builds_summary_by_user_and_recent_attempts(): void
    {
        $article = SalesBookArticle::query()->create([
            'title' => 'Тест «Возражения»',
            'markdown_content' => '# Тест',
            'sort_order' => 0,
        ]);

        $firstUser = User::factory()->create(['name' => 'Иван']);
        $secondUser = User::factory()->create(['name' => 'Мария']);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $firstUser->id,
            'score' => 8,
            'total_questions' => 10,
            'answers' => ['q1' => 'a'],
            'completed_at' => now()->subDay(),
        ]);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $secondUser->id,
            'score' => 10,
            'total_questions' => 10,
            'answers' => ['q1' => 'a'],
            'completed_at' => now(),
        ]);

        $insights = (new SalesBookQuizInsightsService)->insights(30, null, null, 10);

        $this->assertSame(2, $insights['summary']['attempts']);
        $this->assertSame(2, $insights['summary']['unique_users']);
        $this->assertSame(1, $insights['summary']['unique_articles']);
        $this->assertSame(90.0, $insights['summary']['avg_percent']);

        $this->assertCount(1, $insights['by_article']);
        $this->assertSame('Тест «Возражения»', $insights['by_article'][0]['title']);
        $this->assertSame(2, $insights['by_article'][0]['attempts']);

        $this->assertCount(2, $insights['by_user']);
        $byUserNames = array_column($insights['by_user'], 'name');
        $this->assertContains('Иван', $byUserNames);
        $this->assertContains('Мария', $byUserNames);

        $this->assertSame('Мария', $insights['recent_attempts'][0]['user_name']);
        $this->assertSame(100, $insights['recent_attempts'][0]['percent']);
    }

    public function test_it_filters_by_article_id(): void
    {
        $firstArticle = SalesBookArticle::query()->create([
            'title' => 'Первый тест',
            'markdown_content' => '# 1',
            'sort_order' => 0,
        ]);

        $secondArticle = SalesBookArticle::query()->create([
            'title' => 'Второй тест',
            'markdown_content' => '# 2',
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $firstArticle->id,
            'user_id' => $user->id,
            'score' => 5,
            'total_questions' => 10,
            'answers' => [],
            'completed_at' => now(),
        ]);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $secondArticle->id,
            'user_id' => $user->id,
            'score' => 7,
            'total_questions' => 10,
            'answers' => [],
            'completed_at' => now(),
        ]);

        $insights = (new SalesBookQuizInsightsService)->insights(30, $firstArticle->id, null, 10);

        $this->assertSame(1, $insights['summary']['attempts']);
        $this->assertCount(1, $insights['by_article']);
        $this->assertSame($firstArticle->id, $insights['by_article'][0]['article_id']);
        $this->assertSame($firstArticle->id, $insights['recent_attempts'][0]['article_id']);
    }
}
