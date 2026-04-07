<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Проверяет, существует ли колонка в таблице
     */
    private function hasColumn(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return Schema::hasColumn($table, $column);
    }

    public function up(): void
    {
        // Добавляем колонку has_signing_authority в таблицу users
        if (Schema::hasTable('users')) {
            if (! $this->hasColumn('users', 'has_signing_authority')) {
                Schema::table('users', function (Blueprint $table) {
                    // Проверяем существование колонки is_active перед добавлением after
                    if ($this->hasColumn('users', 'is_active')) {
                        $table->boolean('has_signing_authority')
                            ->default(false)
                            ->after('is_active');
                    } else {
                        // Если колонки is_active нет, добавляем без after
                        $table->boolean('has_signing_authority')
                            ->default(false);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Удаляем колонку has_signing_authority из таблицы users
        if (Schema::hasTable('users') && $this->hasColumn('users', 'has_signing_authority')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('has_signing_authority');
                });
            } catch (Throwable $e) {
                // Логируем ошибку, но не прерываем выполнение
                logger()->warning('Failed to drop column has_signing_authority from users: '.$e->getMessage());
            }
        }
    }
};
