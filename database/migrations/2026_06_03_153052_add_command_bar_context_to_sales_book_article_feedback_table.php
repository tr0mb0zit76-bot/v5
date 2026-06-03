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
        if (! Schema::hasTable('sales_book_article_feedback')) {
            return;
        }

        Schema::table('sales_book_article_feedback', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_book_article_feedback', 'turn_id')) {
                $table->uuid('turn_id')->nullable()->after('source');
            }

            if (! Schema::hasColumn('sales_book_article_feedback', 'metadata')) {
                $table->json('metadata')->nullable()->after('turn_id');
            }

            if (! Schema::hasColumn('sales_book_article_feedback', 'turn_id')) {
                return;
            }
        });

        Schema::table('sales_book_article_feedback', function (Blueprint $table) {
            $table->index(['turn_id']);
            $table->index(['source', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_book_article_feedback')) {
            return;
        }

        Schema::table('sales_book_article_feedback', function (Blueprint $table) {
            if (Schema::hasColumn('sales_book_article_feedback', 'turn_id')) {
                $table->dropIndex(['turn_id']);
            }

            if (Schema::hasColumn('sales_book_article_feedback', 'source')) {
                $table->dropIndex(['source', 'created_at']);
            }
        });

        Schema::table('sales_book_article_feedback', function (Blueprint $table) {
            if (Schema::hasColumn('sales_book_article_feedback', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('sales_book_article_feedback', 'turn_id')) {
                $table->dropColumn('turn_id');
            }
        });
    }
};
