<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'customer_payment_form')) {
                $table->string('customer_payment_form', 50)->nullable()->after('target_currency');
            }

            if (! Schema::hasColumn('leads', 'carrier_payment_form')) {
                $table->string('carrier_payment_form', 50)->nullable()->after('customer_payment_form');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (Schema::hasColumn('leads', 'carrier_payment_form')) {
                $table->dropColumn('carrier_payment_form');
            }

            if (Schema::hasColumn('leads', 'customer_payment_form')) {
                $table->dropColumn('customer_payment_form');
            }
        });
    }
};
