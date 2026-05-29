<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'cargo_declared_sum')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('cargo_declared_sum', 15, 2)->nullable()->after('customs_commodity_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'cargo_declared_sum')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('cargo_declared_sum');
        });
    }
};
