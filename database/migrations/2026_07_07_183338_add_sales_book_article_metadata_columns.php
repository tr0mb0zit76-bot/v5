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
        Schema::table('sales_book_articles', function (Blueprint $table) {
            $table->json('properties')->nullable()->after('cover_image_path');
            $table->string('content_format', 24)->default('markdown')->after('properties')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_book_articles', function (Blueprint $table) {
            $table->dropIndex(['content_format']);
            $table->dropColumn(['properties', 'content_format']);
        });
    }
};
