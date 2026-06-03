<?php

namespace App\Services\SalesBook;

use App\Enums\SalesBookArticleFeedbackRating;
use App\Models\SalesBookArticleFeedback;
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
