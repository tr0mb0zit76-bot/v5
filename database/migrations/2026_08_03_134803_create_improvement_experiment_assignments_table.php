<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('improvement_experiments') && ! Schema::hasColumn('improvement_experiments', 'assignment_mode')) {
            Schema::table('improvement_experiments', function (Blueprint $table): void {
                $table->string('assignment_mode', 16)->default('managers')->after('metric_key');
            });
        }

        if (! Schema::hasTable('improvement_experiment_assignments')) {
            Schema::create('improvement_experiment_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('experiment_id')->constrained('improvement_experiments')->cascadeOnDelete();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('variant', 1); // a|b
                $table->string('outcome', 16)->nullable(); // won|lost|null while open
                $table->timestamp('assigned_at');
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['experiment_id', 'lead_id'], 'imp_exp_assign_exp_lead_uq');
                $table->index(['experiment_id', 'variant', 'outcome'], 'imp_exp_assign_exp_var_out_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_experiment_assignments');

        if (Schema::hasTable('improvement_experiments') && Schema::hasColumn('improvement_experiments', 'assignment_mode')) {
            Schema::table('improvement_experiments', function (Blueprint $table): void {
                $table->dropColumn('assignment_mode');
            });
        }
    }
};
