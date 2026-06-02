<?php

namespace App\Services\SalesScripts;

use App\Models\User;
use App\Services\Mcp\SalesBookMcpService;
use App\Support\RoleAccess;

/**
 * Краткая выжимка из Книги продаж для тренажёра (без tool-loop).
 */
final class TrainerSalesBookBriefService
{
    public function __construct(
        private readonly SalesBookMcpService $salesBook,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function buildContextBlock(User $user, string $scriptTitle, string $lastUserMessage, array $history = []): ?string
    {
        if (! RoleAccess::canReadSalesBook($user)) {
            return null;
        }

        $query = $this->buildQuery($scriptTitle, $lastUserMessage, $history);

        if ($query === '') {
            return null;
        }

        try {
            $search = $this->salesBook->search($user, $query, 3);
        } catch (\Throwable) {
            return null;
        }

        $articles = $search['articles'] ?? [];

        if ($articles === []) {
            return null;
        }

        $blocks = [];

        foreach (array_slice($articles, 0, 2) as $summary) {
            $articleId = (int) ($summary['id'] ?? 0);

            if ($articleId <= 0) {
                continue;
            }

            try {
                $detail = $this->salesBook->get($user, $articleId, 1800);
            } catch (\Throwable) {
                continue;
            }

            $title = (string) ($detail['article']['title'] ?? $summary['title'] ?? 'Статья');
            $markdown = trim((string) ($detail['article']['markdown_content'] ?? ''));

            if ($markdown === '') {
                continue;
            }

            $blocks[] = "«{$title}»:\n".$markdown;
        }

        if ($blocks === []) {
            return null;
        }

        return "Справочные факты из внутренней базы (не называй источник и не цитируй дословно заголовки):\n\n"
            .implode("\n\n---\n\n", $blocks);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    private function buildQuery(string $scriptTitle, string $lastUserMessage, array $history): string
    {
        $parts = [];

        $scriptTitle = trim($scriptTitle);

        if ($scriptTitle !== '') {
            $parts[] = $scriptTitle;
        }

        $lastUserMessage = trim($lastUserMessage);

        if ($lastUserMessage !== '' && mb_strlen($lastUserMessage) <= 160) {
            $parts[] = $lastUserMessage;
        }

        foreach (array_slice($history, -4) as $item) {
            if (($item['role'] ?? '') !== 'user') {
                continue;
            }

            $content = trim((string) ($item['content'] ?? ''));

            if ($content !== '' && mb_strlen($content) <= 120) {
                $parts[] = $content;
            }
        }

        $query = trim(implode(' ', array_unique($parts)));

        if ($query === '') {
            return '';
        }

        return mb_substr($query, 0, 120);
    }
}
