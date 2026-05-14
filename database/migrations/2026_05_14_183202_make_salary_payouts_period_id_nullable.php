<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_payouts') || ! Schema::hasColumn('salary_payouts', 'period_id')) {
            return;
        }

        try {
            Schema::table('salary_payouts', function (Blueprint $table): void {
                $table->dropForeign(['period_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('salary_payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('period_id')->nullable()->change();
        });

        if (! Schema::hasTable('salary_periods')) {
            return;
        }

        try {
            Schema::table('salary_payouts', function (Blueprint $table): void {
                $table->foreign('period_id')
                    ->references('id')
                    ->on('salary_periods')
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('salary_payouts') || ! Schema::hasColumn('salary_payouts', 'period_id')) {
            return;
        }

        try {
            Schema::table('salary_payouts', function (Blueprint $table): void {
                $table->dropForeign(['period_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('salary_payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('period_id')->nullable(false)->change();
        });

        if (! Schema::hasTable('salary_periods')) {
            return;
        }

        try {
            Schema::table('salary_payouts', function (Blueprint $table): void {
                $table->foreign('period_id')
                    ->references('id')
                    ->on('salary_periods')
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            //
        }
    }
};
