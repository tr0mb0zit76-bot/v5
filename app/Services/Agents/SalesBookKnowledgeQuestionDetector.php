<?php

namespace App\Services\Agents;

/**
 * Эвристика: вопрос про инструкции / регламент / предмет из Книги продаж.
 */
final class SalesBookKnowledgeQuestionDetector
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function isLikely(string $message, array $history = []): bool
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return false;
        }

        $lower = mb_strtolower($trimmed);

        foreach ($this->directKeywords() as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        if ($this->isFollowUpInKnowledgeContext($trimmed, $history)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function directKeywords(): array
    {
        return [
            'cmr',
            'цмр',
            'инструк',
            'реглам',
            'как заполн',
            'как прикреп',
            'как оформ',
            'какие поля',
            'что такое',
            'что значит',
            'где найти',
            'книга продаж',
            'книге продаж',
            'документ',
            'инвойс',
            'накладн',
            'упд',
            'скрипт',
            'процесс',
            'пошаг',
            'алгоритм',
            'обучен',
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    private function isFollowUpInKnowledgeContext(string $message, array $history): bool
    {
        if (mb_strlen($message) > 120) {
            return false;
        }

        $recent = array_slice($history, -6);

        foreach ($recent as $item) {
            $role = (string) ($item['role'] ?? '');
            $content = mb_strtolower(trim((string) ($item['content'] ?? '')));

            if ($content === '') {
                continue;
            }

            $mentionsBook = str_contains($content, 'книг')
                || str_contains($content, 'стать')
                || str_contains($content, 'cmr')
                || str_contains($content, 'инструк');

            if (! $mentionsBook) {
                continue;
            }

            if ($role === 'assistant' || $role === 'user') {
                return true;
            }
        }

        return false;
    }
}
