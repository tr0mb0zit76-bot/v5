<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('improvement_signals')) {
            Schema::create('improvement_signals', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32)->default('sales');
                $table->string('kind', 64);
                $table->string('severity', 16)->default('info');
                $table->string('title');
                $table->json('payload')->nullable();
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('source', 32)->default('rules');
                $table->string('status', 24)->default('open');
                $table->timestamps();

                $table->index(['domain', 'status']);
                $table->index(['kind', 'created_at']);
            });
        }

        if (! Schema::hasTable('improvement_pipeline_runs')) {
            Schema::create('improvement_pipeline_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('status', 24)->default('running');
                $table->unsignedInteger('signals_used')->default(0);
                $table->unsignedInteger('hypotheses_created')->default(0);
                $table->unsignedInteger('duration_ms')->nullable();
                $table->text('error_summary')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('improvement_hypotheses')) {
            Schema::create('improvement_hypotheses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('signal_id')->nullable()->constrained('improvement_signals')->nullOnDelete();
                $table->foreignId('pipeline_run_id')->nullable()->constrained('improvement_pipeline_runs')->nullOnDelete();
                $table->string('category', 32);
                $table->text('text');
                $table->text('short_reason')->nullable();
                $table->unsignedTinyInteger('impact')->nullable();
                $table->unsignedTinyInteger('confidence')->nullable();
                $table->unsignedTinyInteger('ease')->nullable();
                $table->decimal('score', 8, 2)->nullable();
                $table->string('status', 24)->default('draft');
                $table->string('source', 32)->default('llm_pipeline');
                $table->string('fingerprint', 64)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'score']);
                $table->index('fingerprint');
            });
        }

        if (! Schema::hasTable('improvement_experiments')) {
            Schema::create('improvement_experiments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hypothesis_id')->constrained('improvement_hypotheses')->cascadeOnDelete();
                $table->string('name');
                $table->string('status', 24)->default('planned');
                $table->json('variant_a');
                $table->json('variant_b');
                $table->string('metric_key', 32)->default('win_rate');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->json('cohort')->nullable();
                $table->json('result_snapshot')->nullable();
                $table->string('verdict', 32)->nullable();
                $table->text('verdict_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'starts_on']);
            });
        }

        if (! Schema::hasTable('improvement_adoptions')) {
            Schema::create('improvement_adoptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('experiment_id')->constrained('improvement_experiments')->cascadeOnDelete();
                $table->foreignId('hypothesis_id')->constrained('improvement_hypotheses')->cascadeOnDelete();
                $table->string('target_type', 64);
                $table->unsignedBigInteger('target_id')->nullable();
                $table->text('summary');
                $table->foreignId('adopted_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('adopted_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_adoptions');
        Schema::dropIfExists('improvement_experiments');
        Schema::dropIfExists('improvement_hypotheses');
        Schema::dropIfExists('improvement_pipeline_runs');
        Schema::dropIfExists('improvement_signals');
    }
};
