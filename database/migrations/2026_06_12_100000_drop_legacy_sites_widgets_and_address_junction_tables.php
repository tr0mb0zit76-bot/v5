<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Удаление устаревших сущностей: sites / site_id, виджеты дашборда, неиспользуемые связки адресов.
 * Таблицы ai_* не трогаем (заготовки под ассистентов).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('widget_role_permissions');
        Schema::dropIfExists('user_widgets');
        Schema::dropIfExists('cargo_unloading_points');
        Schema::dropIfExists('contractor_addresses');

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'site_id')) {
            $this->safelyAlterTable('orders', function (Blueprint $table): void {
                $table->dropForeign(['site_id']);
            });
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('site_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'site_id')) {
            $this->safelyAlterTable('users', function (Blueprint $table): void {
                $table->dropForeign(['site_id']);
            });
            $this->safelyAlterTable('users', function (Blueprint $table): void {
                $table->dropIndex(['site_id', 'role_id']);
            });
            $this->safelyAlterTable('users', function (Blueprint $table): void {
                $table->dropIndex('users_site_id_index');
            });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('site_id');
            });
        }

        Schema::dropIfExists('sites');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Не восстанавливаем устаревшую схему: миграция разрушительная.
    }

    /**
     * Blueprint применяется после выхода из замыкания — перехват только снаружи Schema::table.
     */
    private function safelyAlterTable(string $table, callable $using): void
    {
        try {
            Schema::table($table, $using);
        } catch (QueryException) {
            //
        }
    }
};
