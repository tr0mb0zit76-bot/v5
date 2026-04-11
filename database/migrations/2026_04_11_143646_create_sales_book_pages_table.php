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
        Schema::create('sales_book_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_book_id')->constrained('sales_books')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('sales_book_pages')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->json('content')->nullable(); // JSON структура для TipTap
            $table->text('raw_markdown')->nullable(); // Исходный Markdown
            $table->text('excerpt')->nullable();
            $table->integer('order_index')->default(0);
            $table->integer('depth')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sales_book_id', 'slug']);
            $table->index(['sales_book_id', 'parent_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_book_pages');
    }
};
