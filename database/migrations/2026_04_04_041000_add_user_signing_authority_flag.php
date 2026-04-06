<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'has_signing_authority')) {
                    $table->boolean('has_signing_authority')->default(false)->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'has_signing_authority')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('has_signing_authority');
            });
        }
    }
};
