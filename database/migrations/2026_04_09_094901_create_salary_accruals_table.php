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
        if (! Schema::hasTable('salary_accruals')) {
            Schema::create('salary_accruals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('order_id');
                $table->date('order_date_snapshot')->nullable();
                $table->decimal('delta_snapshot', 14, 2)->default(0);
                $table->decimal('salary_amount', 14, 2)->default(0);
                $table->decimal('customer_rate_snapshot', 14, 2)->default(0);
                $table->decimal('paid_customer_amount_at_accrual', 14, 2)->default(0);
                $table->decimal('payable_amount_computed', 14, 2)->default(0);
                $table->decimal('paid_amount_fact', 14, 2)->default(0);
                $table->decimal('unpaid_amount', 14, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['period_id', 'user_id']);
                $table->index(['period_id', 'order_id']);
                $table->unique(['period_id', 'user_id', 'order_id'], 'salary_accruals_unique_period_user_order');
            });
        }

        // Добавляем внешние ключи ПОСЛЕ создания таблицы
        if (Schema::hasTable('salary_accruals') && Schema::hasTable('salary_periods')) {
            if (! $this->foreignKeyExists('salary_accruals', 'period_id')) {
                try {
                    Schema::table('salary_accruals', function (Blueprint $table) {
                        $table->foreign('period_id')
                            ->references('id')
                            ->on('salary_periods')
                            ->cascadeOnDelete();
                    });
                } catch (Throwable $e) {
                    logger()->error('Failed to add foreign key period_id to salary_accruals: '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salary_accruals')) {
            try {
                Schema::table('salary_accruals', function (Blueprint $table) {
                    $table->dropForeign(['period_id']);
                });
            } catch (Throwable $e) {
                logger()->warning('Failed to drop foreign key from salary_accruals: '.$e->getMessage());
            }

            Schema::dropIfExists('salary_accruals');
        }
    }
};
