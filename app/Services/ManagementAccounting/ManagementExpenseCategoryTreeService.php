<?php

namespace App\Services\ManagementAccounting;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagementExpenseCategoryTreeService
{
    /**
     * @return list<array{
     *     id: int,
     *     parent_id: int|null,
     *     code: string,
     *     name: string,
     *     kind: string,
     *     flow: string,
     *     is_system: bool,
     *     is_active: bool,
     *     sort_order: int,
     *     source: string,
     *     include_in_budget: bool,
     *     children: list<mixed>
     * }>
     */
    public function treeForUi(): array
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return [];
        }

        $categories = ManagementExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->buildTree($categories);
    }

    /**
     * @return array{id: int, code: string, name: string}
     */
    public function create(
        string $name,
        ?int $parentId = null,
        string $flow = 'out',
    ): array {
        $parent = $parentId !== null
            ? ManagementExpenseCategory::query()->findOrFail($parentId)
            : null;

        if ($parent !== null && $parent->kind !== 'group') {
            throw ValidationException::withMessages([
                'parent_id' => 'Дочерние статьи можно добавлять только в группу.',
            ]);
        }

        $resolvedFlow = $parent?->flow ?? $flow;
        if (! in_array($resolvedFlow, ['in', 'out'], true)) {
            $resolvedFlow = 'out';
        }

        $baseCode = 'custom_'.Str::slug($name, '_');
        $code = $baseCode !== '' && $baseCode !== 'custom_' ? $baseCode : 'custom_article';
        $suffix = 1;

        while (ManagementExpenseCategory::query()->where('code', $code)->exists()) {
            $code = $baseCode.'_'.$suffix;
            $suffix++;
        }

        $sortOrder = ((int) ManagementExpenseCategory::query()
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->max('sort_order')) + 10;

        $category = ManagementExpenseCategory::query()->create([
            'parent_id' => $parentId,
            'code' => $code,
            'name' => trim($name),
            'kind' => $parentId === null ? 'group' : 'overhead',
            'flow' => $resolvedFlow,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);

        if ($resolvedFlow === 'out' && Schema::hasTable('budget_opex_articles')) {
            $this->ensureBudgetArticleLink($category);
        }

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
        ];
    }

    /**
     * @param  array{name?: string, include_in_budget?: bool}  $payload
     */
    public function update(ManagementExpenseCategory $category, array $payload): void
    {
        if (isset($payload['name'])) {
            if ($category->is_system) {
                throw ValidationException::withMessages([
                    'name' => 'Системную статью нельзя переименовать.',
                ]);
            }

            $category->update(['name' => trim((string) $payload['name'])]);

            if (Schema::hasTable('budget_opex_articles')
                && Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
                BudgetOpexArticle::query()
                    ->where('management_expense_category_id', $category->id)
                    ->update(['name' => $category->name]);
            }
        }

        if (array_key_exists('include_in_budget', $payload)
            && Schema::hasColumn('management_expense_categories', 'include_in_budget')) {
            if ($category->kind === 'group') {
                throw ValidationException::withMessages([
                    'include_in_budget' => 'Для группы нельзя менять участие в бюджете.',
                ]);
            }

            $category->update(['include_in_budget' => (bool) $payload['include_in_budget']]);
        }
    }

    public function delete(ManagementExpenseCategory $category): void
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'Системную статью нельзя удалить.',
            ]);
        }

        if (ManagementExpenseCategory::query()->where('parent_id', $category->id)->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Сначала удалите вложенные статьи.',
            ]);
        }

        if (Schema::hasTable('management_statement_lines')) {
            $hasAllocations = ManagementStatementLine::query()
                ->where('allocation_category_id', $category->id)
                ->exists();

            if ($hasAllocations) {
                throw ValidationException::withMessages([
                    'category' => 'Статья используется в разнесённых операциях.',
                ]);
            }
        }

        if (Schema::hasTable('budget_opex_articles')
            && Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            BudgetOpexArticle::query()
                ->where('management_expense_category_id', $category->id)
                ->delete();
        }

        $category->delete();
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $categories): array
    {
        $byParent = $categories->groupBy(fn (ManagementExpenseCategory $category): int => (int) ($category->parent_id ?? 0));

        $walk = function (int $parentKey) use (&$walk, $byParent): array {
            $nodes = $byParent->get($parentKey, collect());

            return $nodes->map(function (ManagementExpenseCategory $category) use (&$walk): array {
                return [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'kind' => $category->kind,
                    'flow' => $category->flow ?? 'out',
                    'is_system' => $category->is_system,
                    'is_active' => $category->is_active,
                    'sort_order' => $category->sort_order,
                    'source' => $this->resolveSource($category),
                    'include_in_budget' => (bool) ($category->include_in_budget ?? false),
                    'children' => $walk($category->id),
                ];
            })->values()->all();
        };

        return $walk(0);
    }

    private function resolveSource(ManagementExpenseCategory $category): string
    {
        if ($category->is_system) {
            return $category->kind === 'group' ? 'group' : 'system';
        }

        if (str_starts_with($category->code, 'budget_opex_')) {
            return 'budget';
        }

        return 'custom';
    }

    private function ensureBudgetArticleLink(ManagementExpenseCategory $category): void
    {
        $existing = BudgetOpexArticle::query()
            ->where('management_expense_category_id', $category->id)
            ->exists();

        if ($existing) {
            return;
        }

        $maxOrder = (int) BudgetOpexArticle::query()->max('sort_order');

        $article = BudgetOpexArticle::query()->create([
            'name' => $category->name,
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_monthly' => 0,
            'sort_order' => $maxOrder + 10,
            'management_expense_category_id' => $category->id,
        ]);

        $category->forceFill([
            'code' => app(ManagementExpenseCategorySyncService::class)->codeForBudgetOpexArticle($article->id),
        ])->save();
    }
}
