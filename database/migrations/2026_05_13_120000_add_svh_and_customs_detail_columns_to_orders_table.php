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

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'svh_address')) {
                $table->string('svh_address', 500)->nullable()->after('svh_name');
            }
            if (! Schema::hasColumn('orders', 'customs_post_code')) {
                $table->string('customs_post_code', 120)->nullable()->after('svh_address');
            }
            if (! Schema::hasColumn('orders', 'customs_declaration_place')) {
                $table->string('customs_declaration_place', 500)->nullable()->after('customs_post_code');
            }
            if (! Schema::hasColumn('orders', 'customs_commodity_code')) {
                $table->string('customs_commodity_code', 120)->nullable()->after('customs_declaration_place');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            foreach (['customs_commodity_code', 'customs_declaration_place', 'customs_post_code', 'svh_address'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
