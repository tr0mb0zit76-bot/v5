<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'own_company_bank_account_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('own_company_bank_account_id', 100)->nullable()->after('own_company_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'own_company_bank_account_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('own_company_bank_account_id');
            });
        }
    }
};
