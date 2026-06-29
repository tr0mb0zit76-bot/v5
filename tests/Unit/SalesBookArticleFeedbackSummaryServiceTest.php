<?php

namespace Tests\Unit;

use App\Enums\SalesBookArticleFeedbackRating;
use App\Models\User;
use App\Services\SalesBook\SalesBookArticleFeedbackRecorder;
use App\Services\SalesBook\SalesBookArticleFeedbackSummaryService;
use App\Services\SalesBook\SalesBookQualityInsightsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesBookArticleFeedbackSummaryServiceTest extends TestCase
{
    public function test_it_summarizes_single_article_feedback(): void
    {
        $articleId = $this->insertArticle('Перевозчик с НДС');

        $this->insertFeedback($articleId, SalesBookArticleFeedbackRating::Helpful);
        $this->insertFeedback($articleId, SalesBookArticleFeedbackRating::Unclear);
        $this->insertFeedback($articleId, SalesBookArticleFeedbackRating::Outdated);

        $summary = (new SalesBookArticleFeedbackSummaryService)->forArticle($articleId);

        $this->assertSame(1, $summary['helpful']);
        $this->assertSame(1, $summary['unclear']);
        $this->assertSame(1, $summary['outdated']);
        $this->assertSame(3, $summary['total']);
        $this->assertTrue($summary['needs_rewrite']);
    }

    public function test_it_returns_problem_articles_ordered_by_negative_feedback(): void
    {
        $stableArticleId = $this->insertArticle('Понятная статья');
        $problemArticleId = $this->insertArticle('Нужно переписать');

        $this->insertFeedback($stableArticleId, SalesBookArticleFeedbackRating::Helpful);
        $this->insertFeedback($stableArticleId, SalesBookArticleFeedbackRating::Unclear);
        $this->insertFeedback($problemArticleId, SalesBookArticleFeedbackRating::Unclear);
        $this->insertFeedback($problemArticleId, SalesBookArticleFeedbackRating::Outdated);

        $articles = (new SalesBookArticleFeedbackSummaryService)->problemArticles();

        $this->assertSame($problemArticleId, $articles[0]['id']);
        $this->assertSame(2, $articles[0]['negative']);
        $this->assertTrue($articles[0]['needs_rewrite']);
    }

    public function test_it_records_command_bar_feedback_for_read_articles(): void
    {
        $articleId = $this->insertArticle('CMR');
        $user = User::factory()->create();

        $stored = (new SalesBookArticleFeedbackRecorder)->recordCommandBarFeedback(
            $user,
            '01560c3a-7d1c-4a0c-9c8a-5b58e3b38d18',
            'not_helpful',
            'Не хватило шага про печать.',
            [
                'article_ids_read' => [$articleId],
                'gap' => false,
                'gap_reason' => null,
            ],
            'Как оформить CMR?',
        );

        $this->assertSame(1, $stored);

        $row = DB::table('sales_book_article_feedback')->where('sales_book_article_id', $articleId)->first();

        $this->assertSame('unclear', $row->rating);
        $this->assertSame('command_bar', $row->source);
        $this->assertSame('01560c3a-7d1c-4a0c-9c8a-5b58e3b38d18', $row->turn_id);
        $this->assertStringContainsString('Ответ ассистента по этой статье не помог.', $row->comment);

        $articles = (new SalesBookArticleFeedbackSummaryService)->problemArticles();

        $this->assertSame(1, $articles[0]['command_bar']);
    }

    public function test_quality_insights_include_summary_and_recent_feedback(): void
    {
        $articleId = $this->insertArticle('CMR');

        $this->insertFeedback($articleId, SalesBookArticleFeedbackRating::Helpful);
        $this->insertFeedback($articleId, SalesBookArticleFeedbackRating::Unclear, 'web', 'Не хватает примера.');

        $insights = (new SalesBookQualityInsightsService(
            new SalesBookArticleFeedbackSummaryService,
        ))->insights(30, 5);

        $this->assertSame(2, $insights['summary']['feedback_total']);
        $this->assertSame(1, $insights['summary']['unclear']);
        $this->assertSame('CMR', $insights['recent_feedback'][0]['article_title']);
        $this->assertSame('Непонятно', $insights['recent_feedback'][0]['rating_label']);
    }

    private function insertArticle(string $title): int
    {
        return (int) DB::table('sales_book_articles')->insertGetId([
            'title' => $title,
            'markdown_content' => '# '.$title,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertFeedback(
        int $articleId,
        SalesBookArticleFeedbackRating $rating,
        string $source = 'web',
        ?string $comment = null,
    ): void {
        $userId = User::factory()->create()->id;

        DB::table('sales_book_article_feedback')->insert([
            'sales_book_article_id' => $articleId,
            'user_id' => $userId,
            'rating' => $rating->value,
            'comment' => $comment,
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
