<?php

namespace App\Services\SalesBook;

use App\Enums\SalesBookArticleFeedbackRating;
use App\Models\SalesBookArticle;
use App\Models\SalesBookArticleFeedback;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SalesBookArticleFeedbackRecorder
{
    /**
     * @param  array<string, mixed>  $linkedSalesBook
     */
    public function recordCommandBarFeedback(
        User $user,
        string $turnId,
        string $rating,
        ?string $comment,
        array $linkedSalesBook,
        ?string $prompt,
    ): int {
        if (
            ! Schema::hasTable('sales_book_article_feedback')
            || ! Schema::hasColumn('sales_book_article_feedback', 'turn_id')
            || ! Schema::hasColumn('sales_book_article_feedback', 'metadata')
        ) {
            return 0;
        }

        $articleIds = $this->articleIdsFromLinkedSalesBook($linkedSalesBook);

        if ($articleIds === []) {
            return 0;
        }

        $existingArticleIds = SalesBookArticle::query()
            ->whereIn('id', $articleIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $feedbackRating = $rating === 'helpful'
            ? SalesBookArticleFeedbackRating::Helpful
            : SalesBookArticleFeedbackRating::Unclear;

        $stored = 0;

        foreach ($existingArticleIds as $articleId) {
            SalesBookArticleFeedback::query()->updateOrCreate(
                [
                    'sales_book_article_id' => $articleId,
                    'user_id' => $user->id,
                    'source' => 'command_bar',
                    'turn_id' => $turnId,
                ],
                [
                    'rating' => $feedbackRating,
                    'comment' => $this->commandBarComment($rating, $comment, $prompt),
                    'metadata' => [
                        'rating' => $rating,
                        'gap' => (bool) ($linkedSalesBook['gap'] ?? false),
                        'gap_reason' => is_string($linkedSalesBook['gap_reason'] ?? null)
                            ? $linkedSalesBook['gap_reason']
                            : null,
                    ],
                ],
            );

            $stored++;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $linkedSalesBook
     * @return list<int>
     */
    private function articleIdsFromLinkedSalesBook(array $linkedSalesBook): array
    {
        $ids = $linkedSalesBook['article_ids_read'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function commandBarComment(string $rating, ?string $comment, ?string $prompt): string
    {
        $parts = [
            $rating === 'helpful'
                ? 'Ответ ассистента по этой статье был полезен.'
                : 'Ответ ассистента по этой статье не помог.',
        ];

        if ($comment !== null && trim($comment) !== '') {
            $parts[] = 'Комментарий: '.trim($comment);
        }

        if ($prompt !== null && trim($prompt) !== '') {
            $parts[] = 'Вопрос: '.mb_substr(trim($prompt), 0, 500);
        }

        return implode("\n", $parts);
    }
}
