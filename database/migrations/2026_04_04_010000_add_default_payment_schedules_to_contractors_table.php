<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'default_customer_payment_schedule')) {
                $table->json('default_customer_payment_schedule')->nullable()->after('default_customer_payment_term');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_payment_schedule')) {
                $table->json('default_carrier_payment_schedule')->nullable()->after('default_carrier_payment_term');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (Schema::hasColumn('contractors', 'default_customer_payment_schedule')) {
                $table->dropColumn('default_customer_payment_schedule');
            }

            if (Schema::hasColumn('contractors', 'default_carrier_payment_schedule')) {
                $table->dropColumn('default_carrier_payment_schedule');
            }
        });
    }
};
