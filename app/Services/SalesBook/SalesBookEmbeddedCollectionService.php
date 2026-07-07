<?php

namespace App\Services\SalesBook;

use App\Models\SalesBookArticle;
use App\Support\SalesBookPropertyCatalog;
use Illuminate\Support\Collection;

final class SalesBookEmbeddedCollectionService
{
    public function __construct(
        private readonly SalesBookBlockSnapshotService $blockSnapshotService,
        private readonly SalesBookViewService $viewService,
    ) {}

    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return list<array{
     *     block_id: string,
     *     title: string,
     *     view_slug: string,
     *     layout: string,
     *     limit: int,
     *     filters: array<string, mixed>,
     *     rows: list<array<string, mixed>>
     * }>
     */
    public function forArticle(SalesBookArticle $article, Collection $articles): array
    {
        $snapshot = $this->blockSnapshotService->forArticle($article);
        $blocks = $snapshot['blocks'] ?? [];

        if (! is_array($blocks)) {
            return [];
        }

        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'article_collection')
            ->map(fn (array $block): array => $this->collectionPayload($block, $article, $articles))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return array{
     *     block_id: string,
     *     title: string,
     *     view_slug: string,
     *     layout: string,
     *     limit: int,
     *     filters: array<string, mixed>,
     *     rows: list<array<string, mixed>>
     * }
     */
    private function collectionPayload(array $block, SalesBookArticle $article, Collection $articles): array
    {
        $view = $this->viewService->resolve(isset($block['view_slug']) ? (string) $block['view_slug'] : null);
        $filters = [
            ...$view['filters'],
            ...SalesBookPropertyCatalog::normalize($block['filters'] ?? []),
        ];
        $limit = max(1, min(50, (int) ($block['limit'] ?? 8)));
        $rows = $this->viewService
            ->apply($articles, $filters)
            ->reject(fn (SalesBookArticle $candidate): bool => $candidate->id === $article->id)
            ->take($limit)
            ->values();

        return [
            'block_id' => (string) ($block['id'] ?? 'collection'),
            'title' => trim((string) ($block['title'] ?? 'Подборка материалов')) ?: 'Подборка материалов',
            'view_slug' => $view['slug'],
            'layout' => trim((string) ($block['layout'] ?? 'compact')) ?: 'compact',
            'limit' => $limit,
            'filters' => $filters,
            'rows' => $this->viewService->rows($rows, $articles),
        ];
    }
}
