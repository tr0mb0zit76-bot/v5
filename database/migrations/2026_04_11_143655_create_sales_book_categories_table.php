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
        Schema::create('sales_book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('sales_book_categories')->onDelete('cascade');
            $table->integer('order_index')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Связующая таблица для many-to-many связи книг и категорий
        Schema::create('sales_book_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_book_id')->constrained('sales_books')->onDelete('cascade');
            $table->foreignId('sales_book_category_id')->constrained('sales_book_categories')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['sales_book_id', 'sales_book_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_book_category');
        Schema::dropIfExists('sales_book_categories');
    }
};
