<?php

namespace App\Services\Agents;

/**
 * Сводка по обращению к Книге продаж за один ход command bar.
 */
final class SalesBookTurnAnalyzer
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{
     *     knowledge_question: bool,
     *     searched: bool,
     *     results_count: int,
     *     article_ids_read: list<int>,
     *     gap: bool,
     *     gap_reason: string|null
     * }
     */
    public function analyze(array $messages, bool $knowledgeQuestion): array
    {
        $searched = false;
        $resultsCount = 0;
        /** @var list<int> $readIds */
        $readIds = [];

        foreach ($messages as $message) {
            if (($message['role'] ?? '') !== 'tool') {
                continue;
            }

            $payload = json_decode((string) ($message['content'] ?? ''), true);

            if (! is_array($payload)) {
                continue;
            }

            if (array_key_exists('articles', $payload) && is_array($payload['articles'])) {
                $searched = true;
                $resultsCount = max($resultsCount, count($payload['articles']));
            }

            if (isset($payload['article']['id'])) {
                $readIds[] = (int) $payload['article']['id'];
            }
        }

        $readIds = array_values(array_unique($readIds));

        $gap = false;
        $gapReason = null;

        if ($knowledgeQuestion) {
            if (! $searched) {
                $gap = true;
                $gapReason = 'not_searched';
            } elseif ($resultsCount === 0) {
                $gap = true;
                $gapReason = 'no_results';
            } elseif ($readIds === []) {
                $gap = true;
                $gapReason = 'not_read';
            }
        }

        return [
            'knowledge_question' => $knowledgeQuestion,
            'searched' => $searched,
            'results_count' => $resultsCount,
            'article_ids_read' => $readIds,
            'gap' => $gap,
            'gap_reason' => $gapReason,
        ];
    }
}
