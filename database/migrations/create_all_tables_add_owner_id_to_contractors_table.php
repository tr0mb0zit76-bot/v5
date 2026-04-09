<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица contractors создаётся в create_all_tables.php (после всех миграций с числовым префиксом).
 * Колонка owner_id добавляется здесь, чтобы миграция выполнялась после появления таблицы.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors') || Schema::hasColumn('contractors', 'owner_id')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Владелец контрагента (по умолчанию создатель)');
        });

        if (Schema::hasColumn('contractors', 'created_by')) {
            DB::statement('UPDATE contractors SET owner_id = created_by WHERE owner_id IS NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'owner_id')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
