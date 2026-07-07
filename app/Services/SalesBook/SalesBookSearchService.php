<?php

namespace App\Services\SalesBook;

use App\Models\SalesBookArticle;
use App\Support\SalesBookContentNormalizer;
use App\Support\SalesBookPropertyCatalog;

final class SalesBookSearchService
{
    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
        private readonly SalesBookViewService $viewService,
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
    public function search(
        string $query,
        int $limit,
        array $propertyFilters = [],
        ?string $viewSlug = null,
        bool $publishedOnly = true,
    ): array {
        $limit = max(1, min($limit, 50));
        $trimmedQuery = trim($query);
        $activeView = $this->viewService->resolve($viewSlug);
        $filters = array_filter([
            ...$activeView['filters'],
            ...SalesBookPropertyCatalog::normalize($propertyFilters),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $builder = SalesBookArticle::query()
            ->when($publishedOnly, fn ($query) => $query->published())
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($trimmedQuery !== '') {
            $tokens = $this->searchTokens($trimmedQuery);

            if ($tokens !== []) {
                $builder->where(function ($builder) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $builder->orWhere(function ($nested) use ($token): void {
                            $nested->where('title', 'like', '%'.$token.'%')
                                ->orWhere('markdown_content', 'like', '%'.$token.'%')
                                ->orWhere('tags', 'like', '%'.$token.'%');
                        });
                    }
                });
            } else {
                $builder->where(function ($builder) use ($trimmedQuery): void {
                    $builder->where('title', 'like', '%'.$trimmedQuery.'%')
                        ->orWhere('markdown_content', 'like', '%'.$trimmedQuery.'%')
                        ->orWhere('tags', 'like', '%'.$trimmedQuery.'%');
                });
            }
        }

        $fetchLimit = $trimmedQuery !== '' || $filters !== [] ? min(200, max($limit * 5, 50)) : $limit;
        $articles = $builder->limit($fetchLimit)->get();

        if ($filters !== []) {
            $articles = $this->viewService->apply($articles, $filters);
        }

        if ($trimmedQuery !== '') {
            $articles = $articles
                ->sortBy(fn (SalesBookArticle $article): array => [
                    $this->titleMatchScore($article, $trimmedQuery) * -1,
                    mb_stripos($article->title, $trimmedQuery) !== false ? 0 : 1,
                ])
                ->values()
                ->take($limit);
        }

        $articles = $articles->values()->take($limit);

        $parentsById = SalesBookArticle::query()
            ->when($publishedOnly, fn ($query) => $query->published())
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
                'tags' => $this->normalizeTags($article->tags ?? []),
                'properties' => SalesBookPropertyCatalog::normalize($article->properties ?? []),
                'property_labels' => $this->propertyLabels($article),
                'matched_in' => $this->resolveMatchedIn($article, $trimmedQuery),
                'excerpt' => $this->buildSearchExcerpt($article, $trimmedQuery),
            ])->values()->all(),
        ];
    }

    private function resolveMatchedIn(SalesBookArticle $article, string $query): ?string
    {
        if ($query === '') {
            return null;
        }

        if (mb_stripos($article->title, $query) !== false) {
            return 'title';
        }

        foreach ($this->normalizeTags($article->tags ?? []) as $tag) {
            if (mb_stripos($tag, $query) !== false) {
                return 'tags';
            }
        }

        $readerContent = $this->contentNormalizer->forReader((string) ($article->markdown_content ?? ''));

        if (mb_stripos($readerContent, $query) !== false) {
            return 'content';
        }

        return null;
    }

    private function buildSearchExcerpt(SalesBookArticle $article, string $query): ?string
    {
        if ($query === '' || mb_stripos($article->title, $query) !== false) {
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

            if ($part === '' || mb_strlen($part) < 2 || in_array($part, $stopWords, true)) {
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
        $tags = mb_strtolower(implode(' ', $this->normalizeTags($article->tags ?? [])));

        foreach ($this->searchTokens($query) as $token) {
            if (str_contains($title, $token)) {
                $score++;
            }

            if (str_contains($tags, $token)) {
                $score++;
            }
        }

        if (mb_stripos($article->title, $query) !== false) {
            $score += 2;
        }

        return $score;
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
}
