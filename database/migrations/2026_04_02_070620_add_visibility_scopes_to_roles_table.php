<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'visibility_scopes')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->json('visibility_scopes')->nullable()->after('visibility_areas');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_scopes')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('visibility_scopes');
        });
    }
};
