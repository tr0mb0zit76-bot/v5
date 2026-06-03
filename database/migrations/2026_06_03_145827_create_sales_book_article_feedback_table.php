<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_book_article_feedback')) {
            return;
        }

        Schema::create('sales_book_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_book_article_id')->constrained('sales_book_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rating', 32);
            $table->text('comment')->nullable();
            $table->string('source', 32)->default('web');
            $table->timestamps();

            $table->index(['sales_book_article_id', 'rating']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_book_article_feedback');
    }
};
