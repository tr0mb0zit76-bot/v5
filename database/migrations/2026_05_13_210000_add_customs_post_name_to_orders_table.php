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

        if (! Schema::hasColumn('orders', 'customs_post_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('customs_post_name', 255)->nullable()->after('customs_post_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customs_post_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('customs_post_name');
            });
        }
    }
};
