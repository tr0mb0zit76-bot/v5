<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            Schema::create('sales_book_quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_book_article_id')->constrained('sales_book_articles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('score');
                $table->unsignedSmallInteger('total_questions');
                $table->json('answers');
                $table->timestamp('completed_at');
                $table->timestamps();

                $table->index(['sales_book_article_id', 'completed_at'], 'sb_quiz_attempts_article_completed_idx');
                $table->index(['user_id', 'completed_at'], 'sb_quiz_attempts_user_completed_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_book_quiz_attempts');
    }
};
