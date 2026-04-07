<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Владелец контрагента (по умолчанию создатель)');
        });

        // Устанавливаем owner_id = created_by для существующих записей
        DB::statement('UPDATE contractors SET owner_id = created_by WHERE owner_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
