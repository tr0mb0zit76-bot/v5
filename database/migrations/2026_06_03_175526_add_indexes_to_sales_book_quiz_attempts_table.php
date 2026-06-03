<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM sales_book_quiz_attempts'))
            ->pluck('Key_name')
            ->unique();

        if (! $indexes->contains('sb_quiz_attempts_article_completed_idx')) {
            Schema::table('sales_book_quiz_attempts', function (Blueprint $table): void {
                $table->index(['sales_book_article_id', 'completed_at'], 'sb_quiz_attempts_article_completed_idx');
            });
        }

        if (! $indexes->contains('sb_quiz_attempts_user_completed_idx')) {
            Schema::table('sales_book_quiz_attempts', function (Blueprint $table): void {
                $table->index(['user_id', 'completed_at'], 'sb_quiz_attempts_user_completed_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            return;
        }

        Schema::table('sales_book_quiz_attempts', function (Blueprint $table): void {
            $table->dropIndex('sb_quiz_attempts_article_completed_idx');
            $table->dropIndex('sb_quiz_attempts_user_completed_idx');
        });
    }
};
