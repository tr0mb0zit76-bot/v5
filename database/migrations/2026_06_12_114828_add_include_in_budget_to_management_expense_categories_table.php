<?php

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

        Schema::table('management_expense_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('management_expense_categories', 'include_in_budget')) {
                $table->boolean('include_in_budget')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        Schema::table('management_expense_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('management_expense_categories', 'include_in_budget')) {
                $table->dropColumn('include_in_budget');
            }
        });
    }
};
