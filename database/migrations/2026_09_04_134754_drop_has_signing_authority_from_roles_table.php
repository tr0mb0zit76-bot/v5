<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'has_signing_authority')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('has_signing_authority');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'has_signing_authority')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('has_signing_authority')->default(false)->after('columns_config');
        });
    }
};
