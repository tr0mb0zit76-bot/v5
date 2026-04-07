<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

    /**
     * Проверяет, существует ли внешний ключ
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

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
     * Безопасно удаляет внешний ключ
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        $fkName = $this->getForeignKeyName($table, $column);
        if ($fkName && Schema::hasTable($table)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($fkName) {
                    $table->dropForeign($fkName);
                });
            } catch (Throwable $e) {
                logger()->warning("Failed to drop foreign key {$fkName} from {$table}: ".$e->getMessage());
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
        string $onDelete = 'SET NULL'
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($onTable)) {
            return;
        }

        if (! $this->hasColumn($table, $column)) {
            return;
        }

        if (! $this->foreignKeyExists($table, $column)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($column, $references, $onTable, $onDelete) {
                    $table->foreign($column)
                        ->references($references)
                        ->on($onTable)
                        ->onDelete($onDelete);
                });
            } catch (Throwable $e) {
                logger()->warning("Failed to add foreign key {$table}.{$column}: ".$e->getMessage());
            }
        }
    }

    public function up(): void
    {
        // Добавляем колонку has_signing_authority в таблицу roles
        if (Schema::hasTable('roles')) {
            if (! $this->hasColumn('roles', 'has_signing_authority')) {
                Schema::table('roles', function (Blueprint $table) {
                    // Проверяем существование колонки columns_config перед добавлением after
                    if ($this->hasColumn('roles', 'columns_config')) {
                        $table->boolean('has_signing_authority')->default(false)->after('columns_config');
                    } else {
                        $table->boolean('has_signing_authority')->default(false);
                    }
                });
            }

            // Обновляем роль supervisor
            if ($this->hasColumn('roles', 'has_signing_authority') && $this->hasColumn('roles', 'name')) {
                DB::table('roles')
                    ->where('name', 'supervisor')
                    ->update(['has_signing_authority' => true]);
            }
        }

        // Добавляем колонки в таблицу print_form_templates
        if (Schema::hasTable('print_form_templates')) {
            // Сначала добавляем все колонки без внешних ключей
            Schema::table('print_form_templates', function (Blueprint $table) {
                if (! $this->hasColumn('print_form_templates', 'entity_type')) {
                    // Проверяем существование колонки name перед добавлением after
                    if ($this->hasColumn('print_form_templates', 'name')) {
                        $table->string('entity_type', 50)->default('order')->after('name')->index();
                    } else {
                        $table->string('entity_type', 50)->default('order')->index();
                    }
                }

                if (! $this->hasColumn('print_form_templates', 'source_type')) {
                    // Проверяем существование колонки party перед добавлением after
                    if ($this->hasColumn('print_form_templates', 'party')) {
                        $table->string('source_type', 50)->default('system')->after('party')->index();
                    } else {
                        $table->string('source_type', 50)->default('system')->index();
                    }
                }

                if (! $this->hasColumn('print_form_templates', 'is_default')) {
                    $table->boolean('is_default')->default(false)->index();
                }

                if (! $this->hasColumn('print_form_templates', 'file_disk')) {
                    $table->string('file_disk', 50)->nullable();
                }

                if (! $this->hasColumn('print_form_templates', 'file_path')) {
                    $table->string('file_path')->nullable();
                }

                if (! $this->hasColumn('print_form_templates', 'original_filename')) {
                    $table->string('original_filename')->nullable();
                }
            });

            // Добавляем колонку contractor_id отдельно (с внешним ключом)
            if (! $this->hasColumn('print_form_templates', 'contractor_id')) {
                Schema::table('print_form_templates', function (Blueprint $table) {
                    $table->unsignedBigInteger('contractor_id')->nullable();
                });

                // Добавляем внешний ключ, если таблица contractors существует
                if (Schema::hasTable('contractors')) {
                    $this->addForeignKeySafely('print_form_templates', 'contractor_id', 'id', 'contractors', 'SET NULL');
                }
            }
        }
    }

    public function down(): void
    {
        // Удаляем колонки из таблицы print_form_templates
        if (Schema::hasTable('print_form_templates')) {
            // Сначала удаляем внешний ключ contractor_id
            if ($this->hasColumn('print_form_templates', 'contractor_id')) {
                $this->dropForeignKeyIfExists('print_form_templates', 'contractor_id');

                // Удаляем колонку contractor_id
                try {
                    Schema::table('print_form_templates', function (Blueprint $table) {
                        $table->dropColumn('contractor_id');
                    });
                } catch (Throwable $e) {
                    logger()->warning('Failed to drop column contractor_id from print_form_templates: '.$e->getMessage());
                }
            }

            // Удаляем остальные колонки
            $columnsToDrop = [];
            $columns = [
                'entity_type',
                'source_type',
                'is_default',
                'file_disk',
                'file_path',
                'original_filename',
            ];

            foreach ($columns as $column) {
                if ($this->hasColumn('print_form_templates', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                try {
                    Schema::table('print_form_templates', function (Blueprint $table) use ($columnsToDrop) {
                        $table->dropColumn($columnsToDrop);
                    });
                } catch (Throwable $e) {
                    logger()->warning('Failed to drop columns from print_form_templates: '.$e->getMessage());
                }
            }
        }

        // Удаляем колонку has_signing_authority из таблицы roles
        if (Schema::hasTable('roles') && $this->hasColumn('roles', 'has_signing_authority')) {
            try {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropColumn('has_signing_authority');
                });
            } catch (Throwable $e) {
                logger()->warning('Failed to drop column has_signing_authority from roles: '.$e->getMessage());
            }
        }
    }
};
