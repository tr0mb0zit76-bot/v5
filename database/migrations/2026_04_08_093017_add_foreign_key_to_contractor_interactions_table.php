<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем внешний ключ только если таблица contractors существует
        if (Schema::hasTable('contractors') && Schema::hasTable('contractor_interactions')) {
            // Проверяем, существует ли уже внешний ключ
            $connection = Schema::getConnection();
            $dbName = $connection->getDatabaseName();

            $result = $connection->select("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = ? 
                AND TABLE_NAME = 'contractor_interactions' 
                AND CONSTRAINT_NAME = 'contractor_interactions_contractor_id_foreign'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$dbName]);

            if ($result[0]->count == 0) {
                Schema::table('contractor_interactions', function (Blueprint $table) {
                    $table->foreign('contractor_id')
                        ->references('id')
                        ->on('contractors')
                        ->cascadeOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contractor_interactions')) {
            Schema::table('contractor_interactions', function (Blueprint $table) {
                $table->dropForeign(['contractor_id']);
            });
        }
    }
};
