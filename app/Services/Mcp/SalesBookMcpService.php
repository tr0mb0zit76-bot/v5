<?php

namespace App\Services\Mcp;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use App\Models\User;
use App\Services\SalesBook\SalesBookBlockSnapshotService;
use App\Services\SalesBook\SalesBookSearchService;
use App\Services\SalesBookParentChildLinksService;
use App\Support\RoleAccess;
use App\Support\SalesBookContentNormalizer;
use App\Support\SalesBookPropertyCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final class SalesBookMcpService
{
    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
        private readonly SalesBookParentChildLinksService $childLinksService,
        private readonly SalesBookSearchService $searchService,
        private readonly SalesBookBlockSnapshotService $blockSnapshotService,
    ) {}

    /**
     * @return array{articles: list<array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     tags: list<string>,
     *     properties: array<string, mixed>,
     *     property_labels: array<string, string>,
     *     matched_in: string|null,
     *     excerpt: string|null
     * }>}
     */
    public function search(User $user, string $query, int $limit, array $propertyFilters = [], ?string $viewSlug = null): array
    {
        $this->ensureCanRead($user);

        return $this->searchService->search($query, $limit, $propertyFilters, $viewSlug);
    }

    /**
     * @return array{article: array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     breadcrumb: list<array{id: int, title: string}>,
     *     tags: list<string>,
     *     properties: array<string, mixed>,
     *     property_labels: array<string, string>,
     *     markdown_content?: string,
     *     blocks_snapshot?: array<string, mixed>,
     *     content_truncated: bool,
     *     book_url: string,
     *     updated_at: string|null
     * }}
     */
    public function get(User $user, int $articleId, ?int $maxChars = null, string $format = 'markdown'): array
    {
        $this->ensureCanRead($user);

        /** @var SalesBookArticle|null $article */
        $article = SalesBookArticle::query()->published()->find($articleId);

        if ($article === null) {
            throw new ModelNotFoundException('Sales book article not found.');
        }

        $maxChars ??= (int) config('ai.sales_book.article_max_chars', 12000);
        $markdown = $this->contentNormalizer->forReader((string) ($article->markdown_content ?? ''));
        $contentTruncated = false;
        $format = in_array($format, ['markdown', 'blocks', 'both'], true) ? $format : 'markdown';

        if (mb_strlen($markdown) > $maxChars) {
            $markdown = rtrim(mb_substr($markdown, 0, $maxChars))."\n\n…";
            $contentTruncated = true;
        }

        $parentTitle = null;
        if ($article->parent_id !== null) {
            $parentTitle = SalesBookArticle::query()
                ->published()
                ->whereKey($article->parent_id)
                ->value('title');
        }

        $payload = [
            'id' => $article->id,
            'title' => $article->title,
            'parent_id' => $article->parent_id,
            'parent_title' => is_string($parentTitle) ? $parentTitle : null,
            'breadcrumb' => $this->buildBreadcrumb($article),
            'tags' => $this->normalizeTags($article->tags ?? []),
            'properties' => SalesBookPropertyCatalog::normalize($article->properties ?? []),
            'property_labels' => $this->propertyLabels($article),
            'content_truncated' => $contentTruncated,
            'book_url' => route('sales-assistant.book', ['article_id' => $article->id]),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];

        if (in_array($format, ['markdown', 'both'], true)) {
            $payload['markdown_content'] = $markdown;
        }

        if (in_array($format, ['blocks', 'both'], true)) {
            $payload['blocks_snapshot'] = $this->blocksSnapshotFor($article);
        }

        return ['article' => $payload];
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
    public function ensureParentPage(User $user, string $parentTitle, string $markdownContent = ''): SalesBookArticle
    {
        $this->ensureCanWrite($user);

        $parentTitle = trim($parentTitle);

        if ($parentTitle === '') {
            throw new RuntimeException('Заголовок родительской страницы не может быть пустым.');
        }

        $parent = SalesBookArticle::query()
            ->where('title', $parentTitle)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->first();

        if ($parent !== null) {
            return $parent;
        }

        $parent = SalesBookArticle::query()
            ->where('title', $parentTitle)
            ->orderByRaw('parent_id is null desc')
            ->orderBy('id')
            ->first();

        if ($parent !== null) {
            return $parent;
        }

        $markdown = trim($markdownContent);
        if ($markdown === '') {
            $markdown = '# '.$parentTitle;
        }

        $normalizedMarkdown = $this->contentNormalizer->normalize($markdown);

        return SalesBookArticle::query()->create([
            'title' => $parentTitle,
            'markdown_content' => $normalizedMarkdown,
            'parent_id' => null,
            'sort_order' => $this->resolveRootSortOrder(),
            'status' => SalesBookArticleStatus::Published->value,
            'tags' => [],
            'blocks_snapshot' => $this->blockSnapshotService->fromStoredMarkdown($normalizedMarkdown),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function upsertChildPage(
        User $user,
        string $parentTitle,
        string $childTitle,
        string $markdownContent,
        ?int $sortOrder = null,
        array $tags = [],
        bool $createParentIfMissing = false,
        array $blocks = [],
    ): array {
        $this->ensureCanWrite($user);

        $parent = $createParentIfMissing
            ? $this->ensureParentPage($user, $parentTitle)
            : $this->resolveParentByTitle($parentTitle);
        $normalizedMarkdown = $this->contentNormalizer->normalize(
            $blocks !== []
                ? $this->blockSnapshotService->markdownFromBlocks($blocks)
                : $markdownContent,
        );
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
                'tags' => $this->normalizeTags($tags),
                'blocks_snapshot' => $this->blockSnapshotService->fromStoredMarkdown($normalizedMarkdown),
                'updated_by' => $user->id,
            ]);
        } else {
            $article = SalesBookArticle::query()->create([
                'title' => $childTitle,
                'markdown_content' => $normalizedMarkdown,
                'parent_id' => $parent->id,
                'sort_order' => $this->resolveSortOrder($parent->id, $sortOrder),
                'status' => SalesBookArticleStatus::Draft->value,
                'tags' => $this->normalizeTags($tags),
                'blocks_snapshot' => $this->blockSnapshotService->fromStoredMarkdown($normalizedMarkdown),
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
            $parent = SalesBookArticle::query()->published()->find($parentId);

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

    private function resolveRootSortOrder(): int
    {
        $maxSortOrder = (int) SalesBookArticle::query()
            ->whereNull('parent_id')
            ->max('sort_order');

        return $maxSortOrder + 1;
    }

    /**
     * @return array<string, string>
     */
    private function propertyLabels(SalesBookArticle $article): array
    {
        $properties = SalesBookPropertyCatalog::normalize($article->properties ?? []);
        $labels = SalesBookPropertyCatalog::optionLabelsByProperty();

        return collect($properties)
            ->mapWithKeys(function (mixed $value, string $key) use ($labels): array {
                if (is_array($value)) {
                    return [
                        $key => collect($value)
                            ->map(fn (mixed $item): string => $labels[$key][(string) $item] ?? (string) $item)
                            ->implode(', '),
                    ];
                }

                return [
                    $key => $labels[$key][(string) $value] ?? (string) $value,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function blocksSnapshotFor(SalesBookArticle $article): array
    {
        return $this->blockSnapshotService->forArticle($article);
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->map(fn (string $tag): string => mb_substr($tag, 0, 50))
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values()
            ->take(20)
            ->all();
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
