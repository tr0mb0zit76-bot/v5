<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('company_initiatives')) {
            Schema::create('company_initiatives', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('goal')->nullable();
                $table->text('expected_result')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('direction', 120)->nullable()->index();
                $table->date('starts_on')->nullable()->index();
                $table->date('ends_on')->nullable()->index();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->decimal('planned_budget_amount', 15, 2)->nullable();
                $table->string('budget_currency', 3)->default('RUB');
                $table->unsignedBigInteger('management_expense_category_id')->nullable()->index();
                $table->text('budget_notes')->nullable();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->string('risk_level', 20)->default('normal')->index();
                $table->text('risk_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('company_initiative_milestones')) {
            Schema::create('company_initiative_milestones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_initiative_id')->index();
                $table->unsignedBigInteger('responsible_id')->nullable()->index();
                $table->unsignedBigInteger('task_id')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('done_criteria')->nullable();
                $table->string('status', 30)->default('planned')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->date('starts_on')->nullable()->index();
                $table->date('ends_on')->nullable()->index();
                $table->date('completed_on')->nullable();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('company_initiative_dependencies')) {
            Schema::create('company_initiative_dependencies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_initiative_id')->index();
                $table->unsignedBigInteger('blocked_milestone_id')->index();
                $table->unsignedBigInteger('depends_on_milestone_id')->index();
                $table->string('type', 30)->default('finish_to_start');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['blocked_milestone_id', 'depends_on_milestone_id'], 'company_milestone_dependency_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_initiative_dependencies');
        Schema::dropIfExists('company_initiative_milestones');
        Schema::dropIfExists('company_initiatives');
    }
};
