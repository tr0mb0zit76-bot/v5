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
        Schema::table('orders', function (Blueprint $table) {
            $table->date('customer_payment_date')->nullable()->after('customer_payment_term');
            $table->date('carrier_payment_date')->nullable()->after('carrier_payment_term');
            $table->date('additional_expenses_payment_date')->nullable()->after('additional_expenses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_payment_date',
                'carrier_payment_date',
                'additional_expenses_payment_date',
            ]);
        });
    }
};
