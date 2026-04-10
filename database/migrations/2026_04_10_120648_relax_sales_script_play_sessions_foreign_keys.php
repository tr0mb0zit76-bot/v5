<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Если ранее были внешние ключи на contractors/orders — снимаем (опциональные ссылки, тесты без полной схемы).
     */
    public function up(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            try {
                $table->dropForeign(['contractor_id']);
            } catch (Throwable) {
                //
            }
            try {
                $table->dropForeign(['order_id']);
            } catch (Throwable) {
                //
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions') || ! Schema::hasTable('contractors') || ! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            $table->foreign('contractor_id')->references('id')->on('contractors')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }
};
