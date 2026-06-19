<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_scenarios')) {
            return;
        }

        Schema::table('budget_scenarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('budget_scenarios', 'parent_scenario_id')) {
                $table->foreignId('parent_scenario_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('budget_scenarios')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('budget_scenarios', 'plan_type')) {
                $table->string('plan_type', 32)
                    ->default('company')
                    ->after('name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_scenarios')) {
            return;
        }

        Schema::table('budget_scenarios', function (Blueprint $table): void {
            if (Schema::hasColumn('budget_scenarios', 'parent_scenario_id')) {
                $table->dropConstrainedForeignId('parent_scenario_id');
            }

            if (Schema::hasColumn('budget_scenarios', 'plan_type')) {
                $table->dropColumn('plan_type');
            }
        });
    }
};
