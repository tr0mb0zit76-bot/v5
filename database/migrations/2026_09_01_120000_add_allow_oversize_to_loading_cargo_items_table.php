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
        if (! Schema::hasTable('loading_cargo_items')) {
            return;
        }

        Schema::table('loading_cargo_items', function (Blueprint $table) {
            if (! Schema::hasColumn('loading_cargo_items', 'allow_oversize')) {
                $table->boolean('allow_oversize')->default(false)->after('can_tilt');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('loading_cargo_items')) {
            return;
        }

        Schema::table('loading_cargo_items', function (Blueprint $table) {
            if (Schema::hasColumn('loading_cargo_items', 'allow_oversize')) {
                $table->dropColumn('allow_oversize');
            }
        });
    }
};
