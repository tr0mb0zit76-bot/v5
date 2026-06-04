<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_intake_golden_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_intake_draft_id')->constrained('order_intake_drafts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('source_kind', 16)->default('text');
            $table->text('user_instruction')->nullable();
            $table->json('dialog_learnings')->nullable();
            $table->json('proposed_snapshot')->nullable();
            $table->json('applied_snapshot')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique('order_intake_draft_id');
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'committed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_intake_golden_records');
    }
};
