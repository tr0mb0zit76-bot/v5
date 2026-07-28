<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'carrier_own_company_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('carrier_own_company_id')
                ->nullable()
                ->after('own_company_bank_account_id')
                ->constrained('contractors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'carrier_own_company_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_own_company_id');
        });
    }
};
