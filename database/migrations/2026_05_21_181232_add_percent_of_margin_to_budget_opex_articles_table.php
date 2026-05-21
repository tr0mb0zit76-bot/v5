<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_opex_articles')) {
            return;
        }

        Schema::table('budget_opex_articles', function (Blueprint $table): void {
            if (! Schema::hasColumn('budget_opex_articles', 'cost_type')) {
                $table->string('cost_type', 32)->default('fixed_monthly')->after('name');
            }

            if (! Schema::hasColumn('budget_opex_articles', 'percent_of_margin')) {
                $table->decimal('percent_of_margin', 5, 2)->nullable()->after('amount_monthly');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_opex_articles')) {
            return;
        }

        Schema::table('budget_opex_articles', function (Blueprint $table): void {
            if (Schema::hasColumn('budget_opex_articles', 'percent_of_margin')) {
                $table->dropColumn('percent_of_margin');
            }

            if (Schema::hasColumn('budget_opex_articles', 'cost_type')) {
                $table->dropColumn('cost_type');
            }
        });
    }
};
