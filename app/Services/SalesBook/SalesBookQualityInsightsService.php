<?php

namespace App\Services\SalesBook;

use App\Enums\SalesBookArticleFeedbackRating;
use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use App\Models\SalesBookArticleFeedback;
use Illuminate\Support\Facades\Schema;

final class SalesBookQualityInsightsService
{
    public function __construct(
        private readonly SalesBookArticleFeedbackSummaryService $feedbackSummary,
    ) {}

    /**
     * @return array{
     *     summary: array<string, int>,
     *     problem_articles: list<array<string, mixed>>,
     *     recent_feedback: list<array<string, mixed>>,
     *     hints: list<string>
     * }
     */
    public function insights(int $days = 30, int $limit = 10): array
    {
        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 50));

        return [
            'summary' => $this->summary($days),
            'problem_articles' => $this->feedbackSummary->problemArticles($limit),
            'recent_feedback' => $this->recentFeedback($days, $limit),
            'hints' => $this->hints($days),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(int $days): array
    {
        $summary = [
            'articles_total' => 0,
            'published_articles' => 0,
            'draft_articles' => 0,
            'feedback_total' => 0,
            'helpful' => 0,
            'unclear' => 0,
            'outdated' => 0,
            'from_web' => 0,
            'from_command_bar' => 0,
        ];

        if (Schema::hasTable('sales_book_articles')) {
            $articles = SalesBookArticle::query();
            $summary['articles_total'] = (int) (clone $articles)->count();

            if (Schema::hasColumn('sales_book_articles', 'status')) {
                $summary['published_articles'] = (int) (clone $articles)
                    ->where('status', SalesBookArticleStatus::Published->value)
                    ->count();
                $summary['draft_articles'] = (int) (clone $articles)
                    ->where('status', SalesBookArticleStatus::Draft->value)
                    ->count();
            } else {
                $summary['published_articles'] = $summary['articles_total'];
            }
        }

        if (! Schema::hasTable('sales_book_article_feedback')) {
            return $summary;
        }

        $feedback = SalesBookArticleFeedback::query()
            ->where('created_at', '>=', now()->subDays($days));

        $summary['feedback_total'] = (int) (clone $feedback)->count();
        $summary['helpful'] = (int) (clone $feedback)->where('rating', SalesBookArticleFeedbackRating::Helpful->value)->count();
        $summary['unclear'] = (int) (clone $feedback)->where('rating', SalesBookArticleFeedbackRating::Unclear->value)->count();
        $summary['outdated'] = (int) (clone $feedback)->where('rating', SalesBookArticleFeedbackRating::Outdated->value)->count();

        if (Schema::hasColumn('sales_book_article_feedback', 'source')) {
            $summary['from_web'] = (int) (clone $feedback)->where('source', 'web')->count();
            $summary['from_command_bar'] = (int) (clone $feedback)->where('source', 'command_bar')->count();
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFeedback(int $days, int $limit): array
    {
        if (! Schema::hasTable('sales_book_article_feedback')) {
            return [];
        }

        $articleColumns = Schema::hasColumn('sales_book_articles', 'status')
            ? 'article:id,title,status'
            : 'article:id,title';

        return SalesBookArticleFeedback::query()
            ->with([$articleColumns, 'user:id,name'])
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('rating', [
                SalesBookArticleFeedbackRating::Unclear->value,
                SalesBookArticleFeedbackRating::Outdated->value,
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SalesBookArticleFeedback $feedback): array => [
                'id' => $feedback->id,
                'article_id' => $feedback->sales_book_article_id,
                'article_title' => $feedback->article?->title ?? 'Статья удалена',
                'article_status' => $feedback->article?->status?->value,
                'rating' => $feedback->rating?->value,
                'rating_label' => $feedback->rating?->label(),
                'comment' => $feedback->comment,
                'source' => $feedback->source,
                'source_label' => $feedback->source === 'command_bar' ? 'AI' : 'Статья',
                'user_name' => $feedback->user?->name,
                'created_at' => $feedback->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function hints(int $days): array
    {
        $summary = $this->summary($days);
        $hints = [];

        if ($summary['draft_articles'] > 0) {
            $hints[] = 'Есть черновики: проверьте и опубликуйте готовые статьи, чтобы AI мог их цитировать.';
        }

        if ($summary['outdated'] > 0) {
            $hints[] = 'Есть оценки «устарело»: приоритетно обновите регламенты и примеры.';
        }

        if ($summary['unclear'] > $summary['helpful']) {
            $hints[] = 'Оценок «непонятно» больше, чем «полезно»: стоит переписать формулировки проблемных статей.';
        }

        if ($summary['from_command_bar'] > 0) {
            $hints[] = 'Часть сигналов пришла из AI: проверьте статьи, которые ассистент уже использовал в ответах.';
        }

        return $hints;
    }
}
