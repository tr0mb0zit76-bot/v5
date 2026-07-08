<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'precalculation')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('precalculation')->nullable()->after('performers');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'precalculation')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('precalculation');
            });
        }
    }
};
