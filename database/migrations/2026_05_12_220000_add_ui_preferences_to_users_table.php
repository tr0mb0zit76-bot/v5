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

        if (Schema::hasColumn('users', 'mobile_nav_keys')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('ui_preferences')->nullable()->after('mobile_nav_keys');
            });

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->json('ui_preferences')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'ui_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ui_preferences');
        });
    }
};
