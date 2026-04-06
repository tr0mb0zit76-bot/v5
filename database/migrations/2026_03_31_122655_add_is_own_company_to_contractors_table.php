<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors') || Schema::hasColumn('contractors', 'is_own_company')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->boolean('is_own_company')->default(false)->after('is_verified');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'is_own_company')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('is_own_company');
        });
    }
};
