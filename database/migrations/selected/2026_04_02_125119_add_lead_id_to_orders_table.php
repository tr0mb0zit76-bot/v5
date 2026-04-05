<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('leads') || Schema::hasColumn('orders', 'lead_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('carrier_id')->constrained('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'lead_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
