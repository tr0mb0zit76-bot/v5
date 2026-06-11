<?php

use App\Services\ManagementAccounting\ManagementExpenseCategoryHierarchyService;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        app(ManagementExpenseCategorySyncService::class)->ensureSystemCategories();
        app(ManagementExpenseCategoryHierarchyService::class)->ensureDefaultHierarchy();
    }

    public function down(): void
    {
        // Справочник не откатываем.
    }
};
