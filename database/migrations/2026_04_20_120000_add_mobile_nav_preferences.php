<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'mobile_nav_keys')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('mobile_nav_keys')->nullable()->after('ai_preferences');
            });
        }

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->json('default_mobile_nav_keys')->nullable()->after('columns_config');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'mobile_nav_keys')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('mobile_nav_keys');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('default_mobile_nav_keys');
            });
        }
    }
};
