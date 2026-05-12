<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сводка графика оплаты (PaymentScheduleSummaryFormatter) длиннее legacy varchar(50).
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `orders` MODIFY `customer_payment_term` VARCHAR(2000) NULL');

        if (Schema::hasColumn('orders', 'carrier_payment_term')) {
            DB::statement('ALTER TABLE `orders` MODIFY `carrier_payment_term` VARCHAR(2000) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `orders` MODIFY `customer_payment_term` VARCHAR(50) NULL');

        if (Schema::hasColumn('orders', 'carrier_payment_term')) {
            DB::statement('ALTER TABLE `orders` MODIFY `carrier_payment_term` VARCHAR(50) NULL');
        }
    }
};
