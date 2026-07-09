<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_sales_targets')) {
            return;
        }

        Schema::create('budget_sales_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->constrained('budget_scenarios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_month');
            $table->string('metric', 32);
            $table->decimal('planned_value', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['scenario_id', 'user_id', 'period_month', 'metric'], 'budget_sales_targets_unique');
            $table->index(['scenario_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_sales_targets');
    }
};
