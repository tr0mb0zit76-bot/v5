<?php

namespace App\Services\Mcp;

use App\Models\SalesBookArticle;
use App\Models\User;
use App\Services\SalesBookParentChildLinksService;
use App\Support\RoleAccess;
use App\Support\SalesBookContentNormalizer;
use RuntimeException;

final class SalesBookMcpService
{
    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
        private readonly SalesBookParentChildLinksService $childLinksService,
    ) {}

    /**
     * @return array{articles: list<array{id: int, title: string, parent_id: int|null, parent_title: string|null}>}
     */
    public function search(User $user, string $query, int $limit): array
    {
        $this->ensureCanRead($user);

        $builder = SalesBookArticle::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        $trimmedQuery = trim($query);
        if ($trimmedQuery !== '') {
            $builder->where('title', 'like', '%'.$trimmedQuery.'%');
        }

        $articles = $builder->limit(max(1, min($limit, 50)))->get();
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
            ])->values()->all(),
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
