<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_risk_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_version', 16);
            $table->string('status', 16)->default('draft');
            $table->string('outcome', 32)->nullable();
            $table->unsignedTinyInteger('draft_score')->nullable();
            $table->string('draft_grade', 2)->nullable();
            $table->string('draft_tier', 16)->nullable();
            $table->unsignedInteger('draft_recommended_debt_limit_rub')->nullable();
            $table->unsignedTinyInteger('draft_recommended_postpayment_days')->nullable();
            $table->decimal('applied_debt_limit_rub', 14, 2)->nullable();
            $table->unsignedTinyInteger('applied_postpayment_days')->nullable();
            $table->string('applied_schedule_target', 16)->nullable();
            $table->json('edit_delta')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'status', 'created_at'], 'cra_contractor_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_risk_assessments');
    }
};
