<?php

use App\Services\ManagementAccounting\ManagementExpenseCategoryHierarchyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        if (! Schema::hasColumn('management_expense_categories', 'parent_id')) {
            Schema::table('management_expense_categories', function (Blueprint $table): void {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('management_expense_categories', indexName: 'mgmt_exp_cat_parent_fk')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('management_expense_categories', 'flow')) {
            Schema::table('management_expense_categories', function (Blueprint $table): void {
                $table->string('flow', 8)->default('out')->after('kind');
            });
        }

        app(ManagementExpenseCategoryHierarchyService::class)->ensureDefaultHierarchy();
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        if (Schema::hasColumn('management_expense_categories', 'parent_id')) {
            Schema::table('management_expense_categories', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('parent_id');
            });
        }

        if (Schema::hasColumn('management_expense_categories', 'flow')) {
            Schema::table('management_expense_categories', function (Blueprint $table): void {
                $table->dropColumn('flow');
            });
        }
    }
};
