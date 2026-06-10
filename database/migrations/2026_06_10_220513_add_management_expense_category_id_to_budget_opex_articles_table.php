<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasTable('management_expense_categories')) {
            return;
        }

        if (Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            return;
        }

        Schema::table('budget_opex_articles', function (Blueprint $table): void {
            $table->foreignId('management_expense_category_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('management_expense_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            return;
        }

        Schema::table('budget_opex_articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('management_expense_category_id');
        });
    }
};
