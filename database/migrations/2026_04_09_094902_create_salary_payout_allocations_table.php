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
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('salary_payout_allocations')) {
            Schema::create('salary_payout_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payout_id');
                $table->unsignedBigInteger('accrual_id');
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->unique(['payout_id', 'accrual_id']);
            });
        }

        // Добавляем внешние ключи ПОСЛЕ создания таблицы
        if (Schema::hasTable('salary_payout_allocations')) {
            // Внешний ключ для payout_id
            if (Schema::hasTable('salary_payouts') && ! $this->foreignKeyExists('salary_payout_allocations', 'payout_id')) {
                try {
                    Schema::table('salary_payout_allocations', function (Blueprint $table) {
                        $table->foreign('payout_id')
                            ->references('id')
                            ->on('salary_payouts')
                            ->cascadeOnDelete();
                    });
                } catch (Throwable $e) {
                    logger()->error('Failed to add foreign key payout_id to salary_payout_allocations: '.$e->getMessage());
                }
            }

            // Внешний ключ для accrual_id
            if (Schema::hasTable('salary_accruals') && ! $this->foreignKeyExists('salary_payout_allocations', 'accrual_id')) {
                try {
                    Schema::table('salary_payout_allocations', function (Blueprint $table) {
                        $table->foreign('accrual_id')
                            ->references('id')
                            ->on('salary_accruals')
                            ->cascadeOnDelete();
                    });
                } catch (Throwable $e) {
                    logger()->error('Failed to add foreign key accrual_id to salary_payout_allocations: '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salary_payout_allocations')) {
            try {
                Schema::table('salary_payout_allocations', function (Blueprint $table) {
                    $table->dropForeign(['payout_id']);
                    $table->dropForeign(['accrual_id']);
                });
            } catch (Throwable $e) {
                logger()->warning('Failed to drop foreign keys from salary_payout_allocations: '.$e->getMessage());
            }

            Schema::dropIfExists('salary_payout_allocations');
        }
    }
};
