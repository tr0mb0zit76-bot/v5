<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'ui_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('ui_preferences')->nullable()->after('mobile_nav_keys');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'ui_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_preferences');
        });
    }
};
