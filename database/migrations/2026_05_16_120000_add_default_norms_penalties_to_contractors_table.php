<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'default_customer_norms_penalties')) {
                $table->json('default_customer_norms_penalties')->nullable()->after('default_customer_payment_schedule');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_norms_penalties')) {
                $table->json('default_carrier_norms_penalties')->nullable()->after('default_carrier_payment_schedule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            if (Schema::hasColumn('contractors', 'default_customer_norms_penalties')) {
                $table->dropColumn('default_customer_norms_penalties');
            }

            if (Schema::hasColumn('contractors', 'default_carrier_norms_penalties')) {
                $table->dropColumn('default_carrier_norms_penalties');
            }
        });
    }
};
