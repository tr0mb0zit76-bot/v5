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
        if (! Schema::hasTable('payment_schedules') || Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->foreignId('counterparty_id')
                ->nullable()
                ->constrained('contractors')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropForeign(['counterparty_id']);
            $table->dropColumn('counterparty_id');
        });
    }
};
