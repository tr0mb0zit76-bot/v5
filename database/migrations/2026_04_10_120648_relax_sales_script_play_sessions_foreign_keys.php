<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Если ранее были внешние ключи на contractors/orders — снимаем (опциональные ссылки, тесты без полной схемы).
     *
     * Важно: нельзя полагаться на try/catch вокруг Blueprint::dropForeign() — в Laravel ALTER выполняется после
     * выхода из замыкания, исключение MySQL тогда не перехватывается. Снимаем FK только если они реально есть.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropForeignKeysIfExistMysql('sales_script_play_sessions', ['contractor_id', 'order_id']);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignKeysIfExistMysql(string $table, array $columns): void
    {
        $database = Schema::getConnection()->getDatabaseName();

        foreach ($columns as $column) {
            $rows = DB::select(
                'SELECT DISTINCT CONSTRAINT_NAME AS constraint_name
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$database, $table, $column]
            );

            foreach ($rows as $row) {
                $name = $row->constraint_name ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }

                try {
                    DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$name.'`');
                } catch (\Throwable) {
                    //
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions') || ! Schema::hasTable('contractors') || ! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            $table->foreign('contractor_id')->references('id')->on('contractors')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }
};
