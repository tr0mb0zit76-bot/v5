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
        if (! Schema::hasTable('sales_book_articles') || Schema::hasColumn('sales_book_articles', 'cover_image_path')) {
            return;
        }

        Schema::table('sales_book_articles', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_book_articles') || ! Schema::hasColumn('sales_book_articles', 'cover_image_path')) {
            return;
        }

        Schema::table('sales_book_articles', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });
    }
};
