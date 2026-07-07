<?php

namespace App\Services;

use App\Models\SalesBookArticle;
use App\Services\SalesBook\SalesBookBlockSnapshotService;
use Illuminate\Support\Collection;

class SalesBookParentChildLinksService
{
    public const START_MARKER = '<!-- sales-book:child-links -->';

    public const END_MARKER = '<!-- /sales-book:child-links -->';

    public function __construct(
        private SalesBookArticleTreeService $treeService,
        private SalesBookBlockSnapshotService $blockSnapshotService,
    ) {}

    public function articlePath(int $articleId): string
    {
        return route('sales-assistant.book', ['article_id' => $articleId], absolute: false);
    }

    /**
     * @param  list<array{id: int, title: string}>  $children
     */
    public function buildChildLinksBlock(array $children): string
    {
        if ($children === []) {
            return '';
        }

        $lines = array_map(
            fn (array $child): string => sprintf(
                '- [%s](%s)',
                $this->escapeMarkdownLinkText($child['title']),
                $this->articlePath($child['id']),
            ),
            $children,
        );

        return implode("\n", [
            '',
            self::START_MARKER,
            ...$lines,
            self::END_MARKER,
        ]);
    }

    public function mergeChildLinksIntoContent(string $content, int $parentArticleId): string
    {
        $children = $this->loadDirectChildren($parentArticleId);

        return $this->replaceOrAppendChildLinksBlock($content, $this->buildChildLinksBlock($children));
    }

    public function ensureChildLinksSynced(SalesBookArticle $article): void
    {
        if ($this->loadDirectChildren($article->id) === []) {
            return;
        }

        $this->syncParent($article);
    }

    public function syncParent(SalesBookArticle $parent, ?int $updatedBy = null): void
    {
        $children = $this->loadDirectChildren($parent->id);
        $updatedContent = $this->replaceOrAppendChildLinksBlock(
            $parent->markdown_content ?? '',
            $this->buildChildLinksBlock($children),
        );

        if ($updatedContent === ($parent->markdown_content ?? '')) {
            return;
        }

        $parent->update([
            'markdown_content' => $updatedContent,
            'blocks_snapshot' => $this->blockSnapshotService->fromStoredMarkdown($updatedContent),
            'updated_by' => $updatedBy,
        ]);
    }

    public function syncParentById(?int $parentId, ?int $updatedBy = null): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = SalesBookArticle::query()->find($parentId);

        if ($parent === null) {
            return;
        }

        $this->syncParent($parent, $updatedBy);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function loadDirectChildren(int $parentId): array
    {
        /** @var Collection<int, SalesBookArticle> $articles */
        $articles = SalesBookArticle::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->treeService->directChildren($articles, $parentId);
    }

    private function replaceOrAppendChildLinksBlock(string $content, string $newBlock): string
    {
        $pattern = '/\n?'.preg_quote(self::START_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'/s';

        if (preg_match($pattern, $content) === 1) {
            if ($newBlock === '') {
                return trim((string) preg_replace($pattern, '', $content, 1));
            }

            return (string) preg_replace($pattern, $newBlock, $content, 1);
        }

        if ($newBlock === '') {
            return $content;
        }

        if (trim($content) === '') {
            return ltrim($newBlock, "\n");
        }

        return rtrim($content).$newBlock;
    }

    private function escapeMarkdownLinkText(string $text): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $text);
    }
}
