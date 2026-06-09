<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_terms') || ! Schema::hasColumn('financial_terms', 'client_payment_terms')) {
            return;
        }

        DB::statement('ALTER TABLE `financial_terms` MODIFY `client_payment_terms` VARCHAR(400) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_terms') || ! Schema::hasColumn('financial_terms', 'client_payment_terms')) {
            return;
        }

        DB::statement('ALTER TABLE `financial_terms` MODIFY `client_payment_terms` VARCHAR(255) NULL');
    }
};
