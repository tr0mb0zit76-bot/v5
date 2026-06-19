<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_plan_snapshots')) {
            return;
        }

        Schema::create('budget_plan_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->constrained('budget_scenarios')->cascadeOnDelete();
            $table->string('period_label');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('approved_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index('approved_at');
        });

        Schema::create('budget_plan_snapshot_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('budget_plan_snapshots')->cascadeOnDelete();
            $table->date('month');
            $table->foreignId('opex_article_id')->nullable()->constrained('budget_opex_articles')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('management_expense_categories')->nullOnDelete();
            $table->string('article_name');
            $table->decimal('planned_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['snapshot_id', 'month']);
            $table->index(['snapshot_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_snapshot_lines');
        Schema::dropIfExists('budget_plan_snapshots');
    }
};
