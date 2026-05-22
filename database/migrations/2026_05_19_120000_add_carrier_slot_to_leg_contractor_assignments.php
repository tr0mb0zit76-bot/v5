<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leg_contractor_assignments')) {
            return;
        }

        if (! Schema::hasColumn('leg_contractor_assignments', 'carrier_slot')) {
            Schema::table('leg_contractor_assignments', function (Blueprint $table) {
                $table->unsignedTinyInteger('carrier_slot')->default(1)->after('order_leg_id');
            });
        }

        Schema::table('leg_contractor_assignments', function (Blueprint $table) {
            $table->dropForeign(['order_leg_id']);
        });

        Schema::table('leg_contractor_assignments', function (Blueprint $table) {
            $table->dropUnique(['order_leg_id']);
            $table->foreign('order_leg_id')->references('id')->on('order_legs')->cascadeOnDelete();
            $table->unique(['order_leg_id', 'carrier_slot']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leg_contractor_assignments')) {
            return;
        }

        Schema::table('leg_contractor_assignments', function (Blueprint $table) {
            $table->dropForeign(['order_leg_id']);
            $table->dropUnique(['order_leg_id', 'carrier_slot']);
            $table->unique('order_leg_id');
            $table->foreign('order_leg_id')->references('id')->on('order_legs')->cascadeOnDelete();
        });

        if (Schema::hasColumn('leg_contractor_assignments', 'carrier_slot')) {
            Schema::table('leg_contractor_assignments', function (Blueprint $table) {
                $table->dropColumn('carrier_slot');
            });
        }
    }
};
