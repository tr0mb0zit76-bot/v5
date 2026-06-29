<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_ai_suggestion_logs')) {
            return;
        }

        Schema::create('commercial_ai_suggestion_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('suggestion_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('suggestion_type', 32);
            $table->unsignedBigInteger('mail_thread_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('rating', 16)->nullable();
            $table->text('comment')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'suggestion_type']);
            $table->index('mail_thread_id');
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_ai_suggestion_logs');
    }
};
