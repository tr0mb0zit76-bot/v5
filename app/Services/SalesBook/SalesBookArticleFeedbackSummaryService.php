<?php

namespace App\Services\SalesBook;

use App\Enums\SalesBookArticleFeedbackRating;
use App\Models\SalesBookArticle;
use App\Models\SalesBookArticleFeedback;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class SalesBookArticleFeedbackSummaryService
{
    /**
     * @return array{helpful: int, unclear: int, outdated: int, total: int, needs_rewrite: bool}
     */
    public function forArticle(int $articleId): array
    {
        if (! Schema::hasTable('sales_book_article_feedback')) {
            return $this->emptySummary();
        }

        $counts = SalesBookArticleFeedback::query()
            ->where('sales_book_article_id', $articleId)
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $helpful = (int) ($counts[SalesBookArticleFeedbackRating::Helpful->value] ?? 0);
        $unclear = (int) ($counts[SalesBookArticleFeedbackRating::Unclear->value] ?? 0);
        $outdated = (int) ($counts[SalesBookArticleFeedbackRating::Outdated->value] ?? 0);
        $total = $helpful + $unclear + $outdated;

        return [
            'helpful' => $helpful,
            'unclear' => $unclear,
            'outdated' => $outdated,
            'total' => $total,
            'needs_rewrite' => $unclear + $outdated >= 2 && $unclear + $outdated > $helpful,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     title: string,
     *     helpful: int,
     *     unclear: int,
     *     outdated: int,
     *     total: int,
     *     negative: int,
     *     web: int,
     *     command_bar: int,
     *     needs_rewrite: bool,
     *     last_feedback_at: string|null
     * }>
     */
    public function problemArticles(int $limit = 8): array
    {
        if (! Schema::hasTable('sales_book_article_feedback')) {
            return [];
        }

        $article = new SalesBookArticle;
        $feedback = new SalesBookArticleFeedback;
        $articleTable = $article->getTable();
        $feedbackTable = $feedback->getTable();

        /** @var Collection<int, object> $rows */
        $rows = SalesBookArticle::query()
            ->leftJoin($feedbackTable, "{$feedbackTable}.sales_book_article_id", '=', "{$articleTable}.id")
            ->select("{$articleTable}.id", "{$articleTable}.title")
            ->selectRaw(
                'SUM(CASE WHEN '.$feedbackTable.'.rating = ? THEN 1 ELSE 0 END) as helpful_count',
                [SalesBookArticleFeedbackRating::Helpful->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN '.$feedbackTable.'.rating = ? THEN 1 ELSE 0 END) as unclear_count',
                [SalesBookArticleFeedbackRating::Unclear->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN '.$feedbackTable.'.rating = ? THEN 1 ELSE 0 END) as outdated_count',
                [SalesBookArticleFeedbackRating::Outdated->value],
            )
            ->selectRaw("COUNT({$feedbackTable}.id) as total_count")
            ->selectRaw(
                'SUM(CASE WHEN '.$feedbackTable.'.source = ? THEN 1 ELSE 0 END) as command_bar_count',
                ['command_bar'],
            )
            ->selectRaw(
                'SUM(CASE WHEN '.$feedbackTable.'.source = ? THEN 1 ELSE 0 END) as web_count',
                ['web'],
            )
            ->selectRaw("MAX({$feedbackTable}.created_at) as last_feedback_at")
            ->groupBy("{$articleTable}.id", "{$articleTable}.title")
            ->havingRaw('(unclear_count + outdated_count) > 0')
            ->orderByRaw('(unclear_count + outdated_count - helpful_count) DESC')
            ->orderByRaw('(unclear_count + outdated_count) DESC')
            ->orderByDesc('last_feedback_at')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $rows
            ->map(function (object $row): array {
                $helpful = (int) ($row->helpful_count ?? 0);
                $unclear = (int) ($row->unclear_count ?? 0);
                $outdated = (int) ($row->outdated_count ?? 0);
                $negative = $unclear + $outdated;

                return [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'helpful' => $helpful,
                    'unclear' => $unclear,
                    'outdated' => $outdated,
                    'total' => (int) ($row->total_count ?? 0),
                    'negative' => $negative,
                    'web' => (int) ($row->web_count ?? 0),
                    'command_bar' => (int) ($row->command_bar_count ?? 0),
                    'needs_rewrite' => $negative >= 2 && $negative > $helpful,
                    'last_feedback_at' => $row->last_feedback_at !== null ? (string) $row->last_feedback_at : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{helpful: int, unclear: int, outdated: int, total: int, needs_rewrite: bool}
     */
    private function emptySummary(): array
    {
        return [
            'helpful' => 0,
            'unclear' => 0,
            'outdated' => 0,
            'total' => 0,
            'needs_rewrite' => false,
        ];
    }
}
