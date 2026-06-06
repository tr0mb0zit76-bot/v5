<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'ntfy_topic')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('ntfy_topic', 64)->nullable()->after('belongs_to_management');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'ntfy_topic')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ntfy_topic');
        });
    }
};
