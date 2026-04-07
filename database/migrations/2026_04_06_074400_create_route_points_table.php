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
     * Run the migrations.
     */
    public function up(): void
    {
        // Проверяем, существует ли уже таблица
        if (Schema::hasTable('route_points')) {
            return;
        }

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();

            // Внешний ключ для order_leg_id
            if (Schema::hasTable('order_legs')) {
                $table->foreignId('order_leg_id')->constrained('order_legs')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('order_leg_id');
                $table->index('order_leg_id');
            }

            // Внешний ключ для address_id
            if (Schema::hasTable('addresses')) {
                $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('address_id')->nullable();
                $table->index('address_id');
            }

            // Используем string вместо enum для лучшей совместимости
            $table->string('type', 20)->default('transit');
            $table->integer('sequence')->default(0);

            // Сохраняем служебные данные точки
            $table->string('kladr_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->date('planned_date')->nullable();
            $table->time('planned_time_from')->nullable();
            $table->time('planned_time_to')->nullable();

            $table->date('actual_date')->nullable();
            $table->time('actual_time')->nullable();

            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 50)->nullable();

            // Sender/recipient fields
            $table->string('sender_name')->nullable();
            $table->string('sender_contact')->nullable();
            $table->string('sender_phone', 50)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_contact')->nullable();
            $table->string('recipient_phone', 50)->nullable();

            $table->text('instructions')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            // Индексы
            $table->index(['order_leg_id', 'sequence'], 'route_points_order_leg_id_sequence_index');
            $table->index(['order_leg_id', 'type'], 'route_points_order_leg_id_type_index');
            $table->index('type');
            $table->index('planned_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Проверяем, существует ли таблица перед удалением
        if (Schema::hasTable('route_points')) {
            // Удаляем внешние ключи, если они существуют
            if ($this->hasColumn('route_points', 'order_leg_id')) {
                $this->dropForeignKeyIfExists('route_points', 'order_leg_id');
            }

            if ($this->hasColumn('route_points', 'address_id')) {
                $this->dropForeignKeyIfExists('route_points', 'address_id');
            }

            // Удаляем таблицу
            Schema::dropIfExists('route_points');
        }
    }
};
