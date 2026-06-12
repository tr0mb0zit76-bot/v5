<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Support\ManagementExpenseCategoryCatalog;
use Illuminate\Support\Facades\Schema;

class ManagementExpenseCategorySyncService
{
    public function syncAll(): void
    {
        $this->ensureSystemCategories();
        app(ManagementExpenseCategoryHierarchyService::class)->ensureDefaultHierarchy();
    }

    public function ensureSystemCategories(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        foreach (ManagementExpenseCategoryCatalog::systemCategories() as $category) {
            $payload = [
                'name' => $category['name'],
                'kind' => $category['kind'],
                'sort_order' => $category['sort_order'],
                'is_system' => true,
                'is_active' => true,
            ];

            if (Schema::hasColumn('management_expense_categories', 'include_in_budget')) {
                $payload['include_in_budget'] = (bool) ($category['include_in_budget'] ?? false);
            }

            ManagementExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $payload,
            );
        }

        ManagementExpenseCategory::query()
            ->whereIn('code', ManagementExpenseCategoryCatalog::legacyPayrollCodes())
            ->update(['is_active' => false]);
    }

    public function codeForBudgetOpexArticle(int $articleId): string
    {
        return 'budget_opex_'.$articleId;
    }
}
