<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'belongs_to_management')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'has_signing_authority')) {
                $table->boolean('belongs_to_management')
                    ->default(false)
                    ->after('has_signing_authority');
            } else {
                $table->boolean('belongs_to_management')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'belongs_to_management')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('belongs_to_management');
        });
    }
};
