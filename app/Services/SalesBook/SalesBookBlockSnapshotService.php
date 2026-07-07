<?php

namespace App\Services\SalesBook;

use App\Models\SalesBookArticle;
use App\Support\SalesBookContentNormalizer;

final class SalesBookBlockSnapshotService
{
    public const string SCHEMA = 'sales_book_blocks_v1';

    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @return array{
     *     schema: string,
     *     version: int,
     *     source_format: string,
     *     source_sha1: string,
     *     blocks: list<array<string, mixed>>,
     *     stats: array{block_count: int, word_count: int}
     * }
     */
    public function fromStoredMarkdown(string $markdown): array
    {
        $readerMarkdown = $this->contentNormalizer->forReader($markdown);
        $blocks = $this->blocksFromMarkdown($readerMarkdown);
        $quiz = $this->contentNormalizer->parseQuiz($markdown);

        if ($quiz !== null) {
            $blocks[] = $this->block('quiz', count($blocks), [
                'question_count' => count($quiz['questions']),
                'questions' => $quiz['questions'],
            ]);
        }

        return [
            'schema' => self::SCHEMA,
            'version' => 1,
            'source_format' => 'markdown',
            'source_sha1' => sha1($readerMarkdown),
            'blocks' => $blocks,
            'stats' => [
                'block_count' => count($blocks),
                'word_count' => $this->wordCount($readerMarkdown),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forArticle(SalesBookArticle $article): array
    {
        if (is_array($article->blocks_snapshot) && ($article->blocks_snapshot['schema'] ?? null) === self::SCHEMA) {
            return $article->blocks_snapshot;
        }

        return $this->fromStoredMarkdown((string) ($article->markdown_content ?? ''));
    }

    public function stripCollectionDirectives(string $markdown): string
    {
        return trim((string) preg_replace(
            '/\n?```(?:sales-book-view|article_collection)\s+.*?```/s',
            '',
            $markdown,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function markdownFromBlocks(array $blocks): string
    {
        $chunks = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $markdown = $this->blockToMarkdown($block);

            if ($markdown !== '') {
                $chunks[] = $markdown;
            }
        }

        return trim(implode("\n\n", $chunks));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocksFromMarkdown(string $markdown): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($markdown)));
        $blocks = [];
        $paragraph = [];
        $index = 0;
        $lineCount = count($lines);

        while ($index < $lineCount) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $this->flushParagraph($blocks, $paragraph);
                $index++;

                continue;
            }

            if (preg_match('/^```([A-Za-z0-9_-]*)\s*$/', $trimmed, $matches) === 1) {
                $this->flushParagraph($blocks, $paragraph);
                [$block, $index] = $this->consumeCodeBlock($lines, $index, $matches[1] ?? '');

                if ($block !== null) {
                    $blocks[] = $block;
                }

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/u', $trimmed, $matches) === 1) {
                $this->flushParagraph($blocks, $paragraph);
                $blocks[] = $this->block('heading', count($blocks), [
                    'level' => mb_strlen($matches[1]),
                    'text' => trim($matches[2]),
                ]);
                $index++;

                continue;
            }

            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/u', $trimmed, $matches) === 1) {
                $this->flushParagraph($blocks, $paragraph);
                $blocks[] = $this->block('image', count($blocks), [
                    'alt' => trim($matches[1]),
                    'url' => trim($matches[2]),
                ]);
                $index++;

                continue;
            }

            if ($this->isMarkdownTableRow($trimmed)) {
                $this->flushParagraph($blocks, $paragraph);
                [$block, $index] = $this->consumeTable($lines, $index);
                $blocks[] = $block;

                continue;
            }

            if ($this->isListItem($trimmed)) {
                $this->flushParagraph($blocks, $paragraph);
                [$block, $index] = $this->consumeList($lines, $index);
                $blocks[] = $block;

                continue;
            }

            if (str_starts_with($trimmed, '>')) {
                $this->flushParagraph($blocks, $paragraph);
                [$block, $index] = $this->consumeQuote($lines, $index);
                $blocks[] = $block;

                continue;
            }

            $paragraph[] = $trimmed;
            $index++;
        }

        $this->flushParagraph($blocks, $paragraph);

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $paragraph
     */
    private function flushParagraph(array &$blocks, array &$paragraph): void
    {
        if ($paragraph === []) {
            return;
        }

        $text = trim(implode(' ', $paragraph));
        $paragraph = [];

        if ($text === '') {
            return;
        }

        $blocks[] = $this->block('paragraph', count($blocks), [
            'text' => $text,
        ]);
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: array<string, mixed>|null, 1: int}
     */
    private function consumeCodeBlock(array $lines, int $startIndex, string $language): array
    {
        $code = [];
        $index = $startIndex + 1;
        $lineCount = count($lines);

        while ($index < $lineCount) {
            if (trim($lines[$index]) === '```') {
                $index++;

                break;
            }

            $code[] = rtrim($lines[$index]);
            $index++;
        }

        $language = trim($language);
        $codeString = implode("\n", $code);

        if (in_array($language, ['sales-book-view', 'article_collection'], true)) {
            return [
                $this->articleCollectionBlockFromJson($codeString, $startIndex),
                $index,
            ];
        }

        return [
            $this->block('code', $startIndex, [
                'language' => $language ?: null,
                'code' => $codeString,
            ]),
            $index,
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function consumeTable(array $lines, int $startIndex): array
    {
        $rows = [];
        $index = $startIndex;
        $lineCount = count($lines);

        while ($index < $lineCount && $this->isMarkdownTableRow(trim($lines[$index]))) {
            $rows[] = trim($lines[$index]);
            $index++;
        }

        return [
            $this->block('table', $startIndex, [
                'markdown' => implode("\n", $rows),
                'rows' => $rows,
            ]),
            $index,
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function consumeList(array $lines, int $startIndex): array
    {
        $items = [];
        $ordered = false;
        $hasTodo = false;
        $index = $startIndex;
        $lineCount = count($lines);

        while ($index < $lineCount && $this->isListItem(trim($lines[$index]))) {
            $line = trim($lines[$index]);

            preg_match('/^(\d+[.)]|[-*+])\s+(.+)$/u', $line, $matches);
            $marker = $matches[1] ?? '-';
            $text = trim($matches[2] ?? $line);
            $checked = null;

            if (preg_match('/^\[( |x|X)\]\s+(.+)$/u', $text, $todoMatches) === 1) {
                $checked = mb_strtolower($todoMatches[1]) === 'x';
                $text = trim($todoMatches[2]);
                $hasTodo = true;
            }

            if (preg_match('/^\d+[.)]$/', $marker) === 1) {
                $ordered = true;
            }

            $items[] = [
                'text' => $text,
                'checked' => $checked,
            ];
            $index++;
        }

        return [
            $this->block($hasTodo ? 'todo_list' : 'list', $startIndex, [
                'ordered' => $ordered,
                'items' => $items,
            ]),
            $index,
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function consumeQuote(array $lines, int $startIndex): array
    {
        $parts = [];
        $index = $startIndex;
        $lineCount = count($lines);

        while ($index < $lineCount && str_starts_with(trim($lines[$index]), '>')) {
            $parts[] = trim((string) preg_replace('/^>\s?/u', '', trim($lines[$index])));
            $index++;
        }

        return [
            $this->block('quote', $startIndex, [
                'text' => trim(implode("\n", $parts)),
            ]),
            $index,
        ];
    }

    private function isMarkdownTableRow(string $line): bool
    {
        return $line !== ''
            && str_starts_with($line, '|')
            && str_contains(substr($line, 1), '|');
    }

    private function isListItem(string $line): bool
    {
        return preg_match('/^(\d+[.)]|[-*+])\s+.+$/u', $line) === 1;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockToMarkdown(array $block): string
    {
        $type = (string) ($block['type'] ?? 'paragraph');

        return match ($type) {
            'heading' => str_repeat('#', max(1, min(6, (int) ($block['level'] ?? 2)))).' '.trim((string) ($block['text'] ?? '')),
            'paragraph' => trim((string) ($block['text'] ?? '')),
            'quote' => collect(explode("\n", trim((string) ($block['text'] ?? ''))))
                ->map(fn (string $line): string => '> '.$line)
                ->implode("\n"),
            'code' => '```'.trim((string) ($block['language'] ?? ''))."\n".rtrim((string) ($block['code'] ?? ''))."\n```",
            'image' => sprintf('![%s](%s)', trim((string) ($block['alt'] ?? '')), trim((string) ($block['url'] ?? ''))),
            'table' => trim((string) ($block['markdown'] ?? '')),
            'article_collection', 'database_view' => $this->articleCollectionBlockToMarkdown($block),
            'list', 'todo_list' => $this->listBlockToMarkdown($block, $type === 'todo_list'),
            default => trim((string) ($block['text'] ?? '')),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function articleCollectionBlockFromJson(string $json, int $index): ?array
    {
        $decoded = json_decode(trim($json), true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->block('article_collection', $index, $this->normalizeArticleCollectionPayload($decoded));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function articleCollectionBlockToMarkdown(array $block): string
    {
        $payload = $this->normalizeArticleCollectionPayload($block);

        return "```sales-book-view\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n```";
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeArticleCollectionPayload(array $payload): array
    {
        $filters = $payload['filters'] ?? [];

        return [
            'title' => trim((string) ($payload['title'] ?? 'Подборка материалов')) ?: 'Подборка материалов',
            'view_slug' => trim((string) ($payload['view_slug'] ?? 'table')) ?: 'table',
            'layout' => trim((string) ($payload['layout'] ?? 'compact')) ?: 'compact',
            'limit' => max(1, min(50, (int) ($payload['limit'] ?? 8))),
            'filters' => is_array($filters) ? $filters : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function listBlockToMarkdown(array $block, bool $todo): string
    {
        $items = $block['items'] ?? [];

        if (! is_array($items)) {
            return '';
        }

        $ordered = (bool) ($block['ordered'] ?? false);

        return collect($items)
            ->values()
            ->map(function (mixed $item, int $index) use ($ordered, $todo): string {
                $item = is_array($item) ? $item : ['text' => (string) $item];
                $prefix = $ordered ? ($index + 1).'.' : '-';
                $text = trim((string) ($item['text'] ?? ''));

                if ($todo) {
                    $checked = ($item['checked'] ?? false) === true ? 'x' : ' ';
                    $text = sprintf('[%s] %s', $checked, $text);
                }

                return $prefix.' '.$text;
            })
            ->filter(fn (string $line): bool => trim($line) !== '-' && trim($line) !== '')
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function block(string $type, int $index, array $payload): array
    {
        $identity = $type.'|'.$index.'|'.json_encode($payload, JSON_UNESCAPED_UNICODE);

        return [
            'id' => 'b_'.substr(sha1($identity), 0, 12),
            'type' => $type,
            ...$payload,
        ];
    }

    private function wordCount(string $markdown): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $markdown, $matches);

        return count($matches[0] ?? []);
    }
}
