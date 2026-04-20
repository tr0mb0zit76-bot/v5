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
        Schema::table('sales_script_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_script_nodes', 'canvas_x')) {
                $table->integer('canvas_x')->nullable()->after('sort_order');
            }

            if (! Schema::hasColumn('sales_script_nodes', 'canvas_y')) {
                $table->integer('canvas_y')->nullable()->after('canvas_x');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_script_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('sales_script_nodes', 'canvas_y')) {
                $table->dropColumn('canvas_y');
            }

            if (Schema::hasColumn('sales_script_nodes', 'canvas_x')) {
                $table->dropColumn('canvas_x');
            }
        });
    }
};
