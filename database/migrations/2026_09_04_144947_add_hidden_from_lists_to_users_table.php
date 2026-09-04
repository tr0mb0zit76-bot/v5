<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'hidden_from_lists')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('hidden_from_lists')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'hidden_from_lists')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('hidden_from_lists');
        });
    }
};
