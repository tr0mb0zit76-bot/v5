<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_contacts')) {
            // Таблицы нет — создаём с нуля
            Schema::create('contractor_contacts', function (Blueprint $table) {
                $table->id();

                // Проверяем существование таблицы contractors перед добавлением внешнего ключа
                if (Schema::hasTable('contractors')) {
                    $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('contractor_id');
                    $table->index('contractor_id');
                }

                $table->string('full_name');
                $table->string('position')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            // Таблица существует — проверяем каждый столбец и добавляем, если отсутствует
            Schema::table('contractor_contacts', function (Blueprint $table) {
                // Проверяем и добавляем id
                if (! Schema::hasColumn('contractor_contacts', 'id')) {
                    $table->id();
                }

                // Проверяем и добавляем contractor_id
                if (! Schema::hasColumn('contractor_contacts', 'contractor_id')) {
                    // Проверяем существование таблицы contractors перед добавлением внешнего ключа
                    if (Schema::hasTable('contractors')) {
                        $table->foreignId('contractor_id')->after('id')->constrained('contractors')->cascadeOnDelete();
                    } else {
                        $table->unsignedBigInteger('contractor_id')->after('id');
                        $table->index('contractor_id');
                    }
                }

                // Проверяем и добавляем full_name
                if (! Schema::hasColumn('contractor_contacts', 'full_name')) {
                    $table->string('full_name')->after('contractor_id');
                }

                // Проверяем и добавляем position
                if (! Schema::hasColumn('contractor_contacts', 'position')) {
                    $table->string('position')->nullable()->after('full_name');
                }

                // Проверяем и добавляем phone
                if (! Schema::hasColumn('contractor_contacts', 'phone')) {
                    $table->string('phone', 50)->nullable()->after('position');
                }

                // Проверяем и добавляем email
                if (! Schema::hasColumn('contractor_contacts', 'email')) {
                    $table->string('email')->nullable()->after('phone');
                }

                // Проверяем и добавляем is_primary
                if (! Schema::hasColumn('contractor_contacts', 'is_primary')) {
                    $table->boolean('is_primary')->default(false)->after('email');
                }

                // Проверяем и добавляем notes
                if (! Schema::hasColumn('contractor_contacts', 'notes')) {
                    $table->text('notes')->nullable()->after('is_primary');
                }

                // Проверяем наличие полей timestamps (created_at, updated_at)
                if (! Schema::hasColumn('contractor_contacts', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    /**
     * Получить список внешних ключей таблицы
     */
    private function getForeignKeys($table)
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();
        $tableName = $table;

        $results = $conn->select('
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE REFERENCED_TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$dbName, $tableName]);

        return array_column($results, 'CONSTRAINT_NAME');
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_contacts');
    }
};
