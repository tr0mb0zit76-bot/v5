<?php

namespace App\Services;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesBookArticleTreeService
{
    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return Collection<int, array{id:int,title:string,parent_id:int|null,sort_order:int,status:string,children:Collection<int, mixed>}>
     */
    public function buildTree(Collection $articles, ?int $parentId = null): Collection
    {
        return $articles
            ->filter(fn (SalesBookArticle $article): bool => $this->parentMatches($article->parent_id, $parentId))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(function (SalesBookArticle $article) use ($articles): array {
                $children = $this->buildTree($articles, $article->id);

                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'parent_id' => $article->parent_id,
                    'sort_order' => $article->sort_order,
                    'status' => $article->status?->value ?? SalesBookArticleStatus::Published->value,
                    'children' => $children->values()->all(),
                ];
            });
    }

    /**
     * @return list<int>
     */
    public function descendantIds(SalesBookArticle $article): array
    {
        $parentsById = SalesBookArticle::query()->pluck('parent_id', 'id');
        $childrenByParent = [];

        foreach ($parentsById as $childId => $parentId) {
            if ($parentId === null) {
                continue;
            }

            $childrenByParent[(int) $parentId][] = (int) $childId;
        }

        $descendants = [];
        $queue = $childrenByParent[$article->id] ?? [];

        while ($queue !== []) {
            $childId = array_shift($queue);
            $descendants[] = $childId;
            array_push($queue, ...($childrenByParent[$childId] ?? []));
        }

        return $descendants;
    }

    public function isCircularParent(SalesBookArticle $article, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === $article->id) {
            return true;
        }

        return in_array($parentId, $this->descendantIds($article), true);
    }

    public function moveArticle(SalesBookArticle $article, ?int $targetParentId, int $targetIndex, ?int $updatedBy = null): void
    {
        if ($this->isCircularParent($article, $targetParentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Нельзя переместить страницу внутрь собственной вложенной страницы.',
            ]);
        }

        $oldParentId = $article->parent_id;

        $siblings = SalesBookArticle::query()
            ->where('parent_id', $targetParentId)
            ->whereKeyNot($article->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $targetIndex = max(0, min($targetIndex, $siblings->count()));
        $siblings->splice($targetIndex, 0, [$article]);

        foreach ($siblings as $index => $sibling) {
            SalesBookArticle::query()->whereKey($sibling->id)->update([
                'parent_id' => $targetParentId,
                'sort_order' => $index,
                'updated_by' => $updatedBy,
            ]);
        }

        if ($oldParentId !== $targetParentId) {
            $this->reindexSiblings($oldParentId);
        }
    }

    public function reindexSiblings(?int $parentId): void
    {
        $siblings = SalesBookArticle::query()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($siblings as $index => $sibling) {
            if ((int) $sibling->sort_order === $index) {
                continue;
            }

            SalesBookArticle::query()->whereKey($sibling->id)->update([
                'sort_order' => $index,
            ]);
        }
    }

    public function parentMatches(mixed $articleParentId, ?int $expectedParentId): bool
    {
        if ($expectedParentId === null) {
            return $articleParentId === null || $articleParentId === '' || (int) $articleParentId === 0;
        }

        if ($articleParentId === null || $articleParentId === '') {
            return false;
        }

        return (int) $articleParentId === $expectedParentId;
    }

    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return list<array{id: int, title: string}>
     */
    public function directChildren(Collection $articles, int $parentId): array
    {
        return $articles
            ->filter(fn (SalesBookArticle $article): bool => $this->parentMatches($article->parent_id, $parentId))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(fn (SalesBookArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveParentId(array $data): ?int
    {
        if (! array_key_exists('parent_id', $data) || $data['parent_id'] === null || $data['parent_id'] === '') {
            return null;
        }

        return (int) $data['parent_id'];
    }
}
