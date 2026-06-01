<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('event_type', 40);
            $table->string('channel', 24)->nullable();
            $table->string('outcome', 24)->nullable();
            $table->boolean('ok')->default(true);
            $table->string('tool_name', 80)->nullable();
            $table->char('prompt_fingerprint', 64)->nullable();
            $table->text('user_prompt_redacted')->nullable();
            $table->text('assistant_reply_redacted')->nullable();
            $table->json('tools_used')->nullable();
            $table->unsignedSmallInteger('tool_rounds')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tokens_prompt')->nullable();
            $table->unsignedInteger('tokens_completion')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['feature', 'event_type', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index(['prompt_fingerprint', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tool_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interaction_events');
    }
};
