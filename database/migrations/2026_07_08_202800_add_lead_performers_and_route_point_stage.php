<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_route_points') && ! Schema::hasColumn('lead_route_points', 'stage')) {
            Schema::table('lead_route_points', function (Blueprint $table) {
                $table->string('stage', 50)->default('leg_1')->after('type');
            });
        }

        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'performers')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('performers')->nullable()->after('metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_route_points') && Schema::hasColumn('lead_route_points', 'stage')) {
            Schema::table('lead_route_points', function (Blueprint $table) {
                $table->dropColumn('stage');
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'performers')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('performers');
            });
        }
    }
};
