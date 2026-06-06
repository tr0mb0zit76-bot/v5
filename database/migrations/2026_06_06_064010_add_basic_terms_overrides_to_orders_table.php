<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_basic_terms')) {
                $table->json('customer_basic_terms')->nullable()->after('special_notes');
            }

            if (! Schema::hasColumn('orders', 'carrier_basic_terms')) {
                $table->json('carrier_basic_terms')->nullable()->after('customer_basic_terms');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'carrier_basic_terms')) {
                $table->dropColumn('carrier_basic_terms');
            }

            if (Schema::hasColumn('orders', 'customer_basic_terms')) {
                $table->dropColumn('customer_basic_terms');
            }
        });
    }
};
