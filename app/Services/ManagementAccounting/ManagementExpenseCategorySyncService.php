<?php

namespace App\Services\ManagementAccounting;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Support\ManagementExpenseCategoryCatalog;
use Illuminate\Support\Facades\Schema;

class ManagementExpenseCategorySyncService
{
    public function syncAll(): void
    {
        $this->ensureSystemCategories();
        $this->syncFromBudgetOpexArticles();
    }

    public function ensureSystemCategories(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        foreach (ManagementExpenseCategoryCatalog::systemCategories() as $category) {
            ManagementExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'kind' => $category['kind'],
                    'sort_order' => $category['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    public function syncFromBudgetOpexArticles(): void
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasTable('management_expense_categories')) {
            return;
        }

        $hasLinkColumn = Schema::hasColumn('budget_opex_articles', 'management_expense_category_id');

        $columns = ['id', 'name', 'sort_order'];
        if ($hasLinkColumn) {
            $columns[] = 'management_expense_category_id';
        }

        $articles = BudgetOpexArticle::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get($columns);

        foreach ($articles as $article) {
            $code = $this->codeForBudgetOpexArticle($article->id);

            $payload = [
                'name' => $article->name,
                'kind' => 'overhead',
                'is_system' => false,
                'is_active' => true,
                'sort_order' => 200 + (int) $article->sort_order,
            ];

            if (Schema::hasColumn('management_expense_categories', 'flow')) {
                $payload['flow'] = 'out';
            }

            if (Schema::hasColumn('management_expense_categories', 'parent_id')) {
                $payload['parent_id'] = ManagementExpenseCategory::query()
                    ->where('code', 'group_overhead')
                    ->value('id');
            }

            $category = ManagementExpenseCategory::query()->updateOrCreate(
                ['code' => $code],
                $payload,
            );

            if ($hasLinkColumn && $article->management_expense_category_id !== $category->id) {
                $article->forceFill(['management_expense_category_id' => $category->id])->save();
            }
        }
    }

    public function codeForBudgetOpexArticle(int $articleId): string
    {
        return 'budget_opex_'.$articleId;
    }
}
