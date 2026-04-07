<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Проверяет, существует ли внешний ключ
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        if ($conn->getDriverName() === 'mysql') {
            $result = DB::select('
                SELECT COUNT(*) as count 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$database, $table, $column]);

            return $result[0]->count > 0;
        }

        return false;
    }

    /**
     * Получает имя внешнего ключа
     */
    private function getForeignKeyName(string $table, string $column): ?string
    {
        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        if ($conn->getDriverName() === 'mysql') {
            $result = DB::select('
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$database, $table, $column]);

            return ! empty($result) ? $result[0]->CONSTRAINT_NAME : null;
        }

        return null;
    }

    /**
     * Удаляет внешний ключ, если он существует
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if ($this->foreignKeyExists($table, $column)) {
            $fkName = $this->getForeignKeyName($table, $column);
            if ($fkName) {
                try {
                    Schema::table($table, function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                    });
                } catch (Throwable $e) {
                    logger()->error("Failed to drop foreign key {$fkName} from {$table}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Приводит тип колонки к совместимому с leads.id
     */
    private function fixColumnType(string $table, string $column): void
    {
        $conn = Schema::getConnection();

        if ($conn->getDriverName() !== 'mysql') {
            return;
        }

        $database = $conn->getDatabaseName();

        // Получаем тип колонки leads.id
        $leadIdType = DB::select("
            SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'leads' 
            AND COLUMN_NAME = 'id'
        ", [$database]);

        // Получаем тип колонки в целевой таблице
        $columnType = DB::select('
            SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ', [$database, $table, $column]);

        if (empty($leadIdType) || empty($columnType)) {
            return;
        }

        $targetColumnType = $leadIdType[0]->COLUMN_TYPE;
        $currentColumnType = $columnType[0]->COLUMN_TYPE;
        $isNullable = $columnType[0]->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';

        // Если типы не совпадают, изменяем колонку
        if ($targetColumnType !== $currentColumnType) {
            // Сначала удаляем внешний ключ, если он есть
            $this->dropForeignKeyIfExists($table, $column);

            // Изменяем тип колонки
            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$targetColumnType} {$isNullable}");
                logger()->info("Changed column type of {$table}.{$column} to {$targetColumnType}");
            } catch (Throwable $e) {
                logger()->error("Failed to change column type of {$table}.{$column}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Безопасно добавляет внешний ключ
     */
    private function addForeignKeySafely(
        string $table,
        string $column,
        string $references,
        string $onTable,
        string $onDelete = 'CASCADE'
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($onTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        // Проверяем и исправляем тип колонки
        $this->fixColumnType($table, $column);

        // Добавляем внешний ключ, если его нет
        if (! $this->foreignKeyExists($table, $column)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($column, $references, $onTable, $onDelete) {
                    $table->foreign($column)
                        ->references($references)
                        ->on($onTable)
                        ->onDelete($onDelete);
                });
                logger()->info("Added foreign key {$table}.{$column} -> {$onTable}.{$references}");
            } catch (Throwable $e) {
                logger()->error("Failed to add foreign key {$table}.{$column}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Безопасно удаляет внешний ключ
     */
    private function dropForeignKeySafely(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->foreignKeyExists($table, $column)) {
            $this->dropForeignKeyIfExists($table, $column);
            logger()->info("Dropped foreign key {$table}.{$column}");
        }
    }

    public function up(): void
    {
        // Проверяем существование таблицы leads
        if (! Schema::hasTable('leads')) {
            logger()->warning("Table 'leads' does not exist, skipping foreign keys creation");

            return;
        }

        // Добавляем внешние ключи для таблицы leads
        Schema::table('leads', function (Blueprint $table) {
            // counterparty_id -> contractors.id
            if (Schema::hasColumn('leads', 'counterparty_id') && Schema::hasTable('contractors')) {
                if (! $this->foreignKeyExists('leads', 'counterparty_id')) {
                    try {
                        $table->foreign('counterparty_id')
                            ->references('id')
                            ->on('contractors')
                            ->nullOnDelete();
                    } catch (Throwable $e) {
                        logger()->error('Failed to add foreign key counterparty_id to leads: '.$e->getMessage());
                    }
                }
            }

            // responsible_id -> users.id
            if (Schema::hasColumn('leads', 'responsible_id') && Schema::hasTable('users')) {
                if (! $this->foreignKeyExists('leads', 'responsible_id')) {
                    try {
                        $table->foreign('responsible_id')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    } catch (Throwable $e) {
                        logger()->error('Failed to add foreign key responsible_id to leads: '.$e->getMessage());
                    }
                }
            }

            // created_by -> users.id
            if (Schema::hasColumn('leads', 'created_by') && Schema::hasTable('users')) {
                if (! $this->foreignKeyExists('leads', 'created_by')) {
                    try {
                        $table->foreign('created_by')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    } catch (Throwable $e) {
                        logger()->error('Failed to add foreign key created_by to leads: '.$e->getMessage());
                    }
                }
            }

            // updated_by -> users.id
            if (Schema::hasColumn('leads', 'updated_by') && Schema::hasTable('users')) {
                if (! $this->foreignKeyExists('leads', 'updated_by')) {
                    try {
                        $table->foreign('updated_by')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    } catch (Throwable $e) {
                        logger()->error('Failed to add foreign key updated_by to leads: '.$e->getMessage());
                    }
                }
            }
        });

        // Добавляем внешние ключи для связанных таблиц
        $relatedTables = [
            'lead_route_points' => ['lead_id' => ['onDelete' => 'CASCADE']],
            'lead_cargo_items' => ['lead_id' => ['onDelete' => 'CASCADE']],
            'lead_activities' => [
                'lead_id' => ['onDelete' => 'CASCADE'],
                'created_by' => ['onDelete' => 'SET NULL'],
            ],
            'lead_offers' => [
                'lead_id' => ['onDelete' => 'CASCADE'],
                'created_by' => ['onDelete' => 'SET NULL'],
            ],
        ];

        foreach ($relatedTables as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column => $config) {
                    $onDelete = $config['onDelete'] === 'CASCADE' ? 'CASCADE' : 'SET NULL';

                    if ($column === 'lead_id') {
                        $this->addForeignKeySafely($table, $column, 'id', 'leads', $onDelete);
                    } elseif ($column === 'created_by' && Schema::hasTable('users')) {
                        $this->addForeignKeySafely($table, $column, 'id', 'users', $onDelete);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Удаляем внешние ключи в обратном порядке
        $relatedTables = [
            'lead_offers' => ['lead_id', 'created_by'],
            'lead_activities' => ['lead_id', 'created_by'],
            'lead_cargo_items' => ['lead_id'],
            'lead_route_points' => ['lead_id'],
        ];

        foreach ($relatedTables as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    $this->dropForeignKeySafely($table, $column);
                }
            }
        }

        // Удаляем внешние ключи из таблицы leads
        if (Schema::hasTable('leads')) {
            $leadColumns = ['counterparty_id', 'responsible_id', 'created_by', 'updated_by'];
            foreach ($leadColumns as $column) {
                $this->dropForeignKeySafely('leads', $column);
            }
        }
    }
};
