<?php

namespace App\Services\SalesBook;

use App\Models\SalesBookArticle;
use App\Support\SalesBookPropertyCatalog;
use Illuminate\Support\Collection;

final class SalesBookViewService
{
    /**
     * @return list<array{
     *     slug: string,
     *     label: string,
     *     description: string,
     *     layout: string,
     *     filters: array<string, mixed>
     * }>
     */
    public function systemViews(): array
    {
        return [
            [
                'slug' => 'tree',
                'label' => 'Дерево',
                'description' => 'Обычная структура страниц.',
                'layout' => 'tree',
                'filters' => [],
            ],
            [
                'slug' => 'table',
                'label' => 'Таблица',
                'description' => 'Плоский список со свойствами.',
                'layout' => 'table',
                'filters' => [],
            ],
            [
                'slug' => 'by-stage',
                'label' => 'По этапам',
                'description' => 'Материалы сгруппированы по этапу продаж.',
                'layout' => 'stage',
                'filters' => [],
            ],
            [
                'slug' => 'manager-materials',
                'label' => 'Для менеджера',
                'description' => 'Статьи, помеченные как материалы для менеджера.',
                'layout' => 'table',
                'filters' => [
                    'audience_role' => 'manager',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     slug: string,
     *     label: string,
     *     description: string,
     *     layout: string,
     *     filters: array<string, mixed>
     * }
     */
    public function resolve(?string $slug): array
    {
        $slug = trim((string) $slug);

        return collect($this->systemViews())
            ->firstWhere('slug', $slug)
            ?? $this->systemViews()[0];
    }

    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return Collection<int, SalesBookArticle>
     */
    public function apply(Collection $articles, array $filters): Collection
    {
        if ($filters === []) {
            return $articles->values();
        }

        return $articles
            ->filter(fn (SalesBookArticle $article): bool => $this->articleMatches($article, $filters))
            ->values();
    }

    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     * @return list<array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     status: string,
     *     tags: list<string>,
     *     properties: array<string, mixed>,
     *     property_labels: array<string, string>
     * }>
     */
    public function rows(Collection $articles, ?Collection $parentSource = null): array
    {
        $parentsById = ($parentSource ?? $articles)
            ->pluck('title', 'id')
            ->all();

        return $articles
            ->map(fn (SalesBookArticle $article): array => $this->row($article, $parentsById))
            ->values()
            ->all();
    }

    private function articleMatches(SalesBookArticle $article, array $filters): bool
    {
        $properties = SalesBookPropertyCatalog::normalize($article->properties ?? []);

        foreach ($filters as $key => $expected) {
            $actual = $properties[$key] ?? null;
            $expectedValues = is_array($expected) ? $expected : [$expected];

            if (is_array($actual)) {
                if (array_intersect($expectedValues, $actual) === []) {
                    return false;
                }

                continue;
            }

            if (! in_array($actual, $expectedValues, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int|string, string>  $parentsById
     * @return array{
     *     id: int,
     *     title: string,
     *     parent_id: int|null,
     *     parent_title: string|null,
     *     status: string,
     *     tags: list<string>,
     *     properties: array<string, mixed>,
     *     property_labels: array<string, string>
     * }
     */
    private function row(SalesBookArticle $article, array $parentsById): array
    {
        $properties = SalesBookPropertyCatalog::normalize($article->properties ?? []);
        $labels = SalesBookPropertyCatalog::optionLabelsByProperty();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'parent_id' => $article->parent_id,
            'parent_title' => $article->parent_id !== null ? ($parentsById[$article->parent_id] ?? null) : null,
            'status' => $article->status?->value ?? 'published',
            'tags' => $this->normalizeTags($article->tags ?? []),
            'properties' => $properties,
            'property_labels' => collect($properties)
                ->mapWithKeys(fn (mixed $value, string $key): array => [
                    $key => $this->labelFor($labels[$key] ?? [], $value),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function labelFor(array $labels, mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => $labels[(string) $item] ?? (string) $item)
                ->implode(', ');
        }

        return $labels[(string) $value] ?? (string) $value;
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
            ->values()
            ->all();
    }
}
