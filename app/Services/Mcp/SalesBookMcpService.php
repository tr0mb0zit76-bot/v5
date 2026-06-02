<?php

namespace App\Services\Mcp;

use App\Models\SalesBookArticle;
use App\Models\User;
use App\Services\SalesBookParentChildLinksService;
use App\Support\RoleAccess;
use App\Support\SalesBookContentNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final class SalesBookMcpService
{
    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
        private readonly SalesBookParentChildLinksService $childLinksService,
    ) {}

    /**
     * @return array{articles: list<array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     matched_in: string|null,
     *     excerpt: string|null
     * }>}
     */
    public function search(User $user, string $query, int $limit): array
    {
        $this->ensureCanRead($user);

        $limit = max(1, min($limit, 50));
        $trimmedQuery = trim($query);

        $builder = SalesBookArticle::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($trimmedQuery !== '') {
            $tokens = $this->searchTokens($trimmedQuery);

            if ($tokens !== []) {
                $builder->where(function ($builder) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $builder->orWhere(function ($nested) use ($token): void {
                            $nested->where('title', 'like', '%'.$token.'%')
                                ->orWhere('markdown_content', 'like', '%'.$token.'%');
                        });
                    }
                });
            } else {
                $builder->where(function ($builder) use ($trimmedQuery): void {
                    $builder->where('title', 'like', '%'.$trimmedQuery.'%')
                        ->orWhere('markdown_content', 'like', '%'.$trimmedQuery.'%');
                });
            }
        }

        $fetchLimit = $trimmedQuery !== '' ? min(100, $limit * 3) : $limit;
        $articles = $builder->limit($fetchLimit)->get();

        if ($trimmedQuery !== '') {
            $articles = $articles
                ->sortBy(fn (SalesBookArticle $article): array => [
                    $this->titleMatchScore($article, $trimmedQuery) * -1,
                    mb_stripos($article->title, $trimmedQuery) !== false ? 0 : 1,
                ])
                ->values()
                ->take($limit);
        }

        $parentsById = SalesBookArticle::query()
            ->whereIn('id', $articles->pluck('parent_id')->filter()->unique())
            ->pluck('title', 'id');

        return [
            'articles' => $articles->map(fn (SalesBookArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'parent_id' => $article->parent_id,
                'parent_title' => $article->parent_id !== null
                    ? (string) ($parentsById[$article->parent_id] ?? null)
                    : null,
                'matched_in' => $this->resolveMatchedIn($article, $trimmedQuery),
                'excerpt' => $this->buildSearchExcerpt($article, $trimmedQuery),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{article: array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     breadcrumb: list<array{id: int, title: string}>,
     *     markdown_content: string,
     *     content_truncated: bool,
     *     book_url: string,
     *     updated_at: string|null
     * }}
     */
    public function get(User $user, int $articleId, ?int $maxChars = null): array
    {
        $this->ensureCanRead($user);

        /** @var SalesBookArticle|null $article */
        $article = SalesBookArticle::query()->find($articleId);

        if ($article === null) {
            throw new ModelNotFoundException('Sales book article not found.');
        }

        $maxChars ??= (int) config('ai.sales_book.article_max_chars', 12000);
        $markdown = $this->contentNormalizer->forReader((string) ($article->markdown_content ?? ''));
        $contentTruncated = false;

        if (mb_strlen($markdown) > $maxChars) {
            $markdown = rtrim(mb_substr($markdown, 0, $maxChars))."\n\n…";
            $contentTruncated = true;
        }

        $parentTitle = null;
        if ($article->parent_id !== null) {
            $parentTitle = SalesBookArticle::query()
                ->whereKey($article->parent_id)
                ->value('title');
        }

        return [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'parent_id' => $article->parent_id,
                'parent_title' => is_string($parentTitle) ? $parentTitle : null,
                'breadcrumb' => $this->buildBreadcrumb($article),
                'markdown_content' => $markdown,
                'content_truncated' => $contentTruncated,
                'book_url' => route('sales-assistant.book', ['article_id' => $article->id]),
                'updated_at' => $article->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{
     *     action: string,
     *     article_id: int,
     *     title: string,
     *     parent_id: int,
     *     parent_title: string,
     *     book_url: string
     * }
     */
    public function upsertChildPage(
        User $user,
        string $parentTitle,
        string $childTitle,
        string $markdownContent,
        ?int $sortOrder = null,
    ): array {
        $this->ensureCanWrite($user);

        $parent = $this->resolveParentByTitle($parentTitle);
        $normalizedMarkdown = $this->contentNormalizer->normalize($markdownContent);
        $childTitle = trim($childTitle);

        $article = SalesBookArticle::query()
            ->where('parent_id', $parent->id)
            ->where('title', $childTitle)
            ->first();

        $action = 'created';

        if ($article !== null) {
            $action = 'updated';
            $article->update([
                'markdown_content' => $normalizedMarkdown,
                'sort_order' => $sortOrder ?? $article->sort_order,
                'updated_by' => $user->id,
            ]);
        } else {
            $article = SalesBookArticle::query()->create([
                'title' => $childTitle,
                'markdown_content' => $normalizedMarkdown,
                'parent_id' => $parent->id,
                'sort_order' => $this->resolveSortOrder($parent->id, $sortOrder),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $this->childLinksService->syncParentById($parent->id, $user->id);

        return [
            'action' => $action,
            'article_id' => $article->id,
            'title' => $article->title,
            'parent_id' => $parent->id,
            'parent_title' => $parent->title,
            'book_url' => route('sales-assistant.book', ['article_id' => $article->id]),
        ];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function buildBreadcrumb(SalesBookArticle $article): array
    {
        $trail = [];
        $parentId = $article->parent_id;

        while ($parentId !== null) {
            /** @var SalesBookArticle|null $parent */
            $parent = SalesBookArticle::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($trail, [
                'id' => $parent->id,
                'title' => $parent->title,
            ]);

            $parentId = $parent->parent_id;
        }

        return $trail;
    }

    private function resolveMatchedIn(SalesBookArticle $article, string $query): ?string
    {
        if ($query === '') {
            return null;
        }

        if (mb_stripos($article->title, $query) !== false) {
            return 'title';
        }

        $readerContent = $this->contentNormalizer->forReader((string) ($article->markdown_content ?? ''));

        if (mb_stripos($readerContent, $query) !== false) {
            return 'content';
        }

        return null;
    }

    private function buildSearchExcerpt(SalesBookArticle $article, string $query): ?string
    {
        if ($query === '') {
            return null;
        }

        if (mb_stripos($article->title, $query) !== false) {
            return null;
        }

        $readerContent = $this->contentNormalizer->forReader((string) ($article->markdown_content ?? ''));
        $position = mb_stripos($readerContent, $query);

        if ($position === false) {
            return null;
        }

        $maxChars = (int) config('ai.sales_book.excerpt_chars', 240);
        $start = max(0, $position - (int) ($maxChars / 3));
        $excerpt = mb_substr($readerContent, $start, $maxChars);
        $excerpt = preg_replace('/\s+/u', ' ', trim($excerpt)) ?? '';

        $prefix = $start > 0 ? '…' : '';
        $suffix = mb_strlen($readerContent) > $start + $maxChars ? '…' : '';

        return $prefix.$excerpt.$suffix;
    }

    /**
     * @return list<string>
     */
    private function searchTokens(string $query): array
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query) ?? '');

        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];
        $stopWords = ['как', 'что', 'где', 'или', 'для', 'про', 'это', 'the', 'and'];

        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }

            if (in_array($part, $stopWords, true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function titleMatchScore(SalesBookArticle $article, string $query): int
    {
        $score = 0;
        $title = mb_strtolower($article->title);

        foreach ($this->searchTokens($query) as $token) {
            if (str_contains($title, $token)) {
                $score++;
            }
        }

        if (mb_stripos($article->title, $query) !== false) {
            $score += 2;
        }

        return $score;
    }

    private function resolveParentByTitle(string $parentTitle): SalesBookArticle
    {
        $parentTitle = trim($parentTitle);

        $candidates = SalesBookArticle::query()
            ->where('title', $parentTitle)
            ->orderByRaw('parent_id is null desc')
            ->orderBy('id')
            ->get();

        $parent = $candidates->first();

        if ($parent === null) {
            throw new RuntimeException(sprintf(
                'Родительская страница «%s» не найдена в Книге продаж. Создайте её вручную в CRM.',
                $parentTitle,
            ));
        }

        return $parent;
    }

    private function resolveSortOrder(int $parentId, ?int $requestedSortOrder): int
    {
        if ($requestedSortOrder !== null) {
            return max(0, $requestedSortOrder);
        }

        $maxSortOrder = (int) SalesBookArticle::query()
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return $maxSortOrder + 1;
    }

    private function ensureCanRead(User $user): void
    {
        if (! RoleAccess::canReadSalesBook($user)) {
            throw new RuntimeException('Нет доступа к чтению Книги продаж.');
        }
    }

    private function ensureCanWrite(User $user): void
    {
        if (! RoleAccess::canWriteSalesBook($user)) {
            throw new RuntimeException('Нет права sales_book_write для изменения Книги продаж.');
        }
    }
}
