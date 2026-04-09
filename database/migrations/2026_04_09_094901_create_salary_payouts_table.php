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
        if (! Schema::hasTable('salary_payouts')) {
            Schema::create('salary_payouts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id');
                $table->unsignedBigInteger('user_id');
                $table->decimal('amount', 14, 2);
                $table->date('payout_date');
                $table->string('type', 20)->default('salary');
                $table->text('comment')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['period_id', 'user_id']);
                $table->index(['payout_date']);
            });
        }

        // Добавляем внешние ключи ПОСЛЕ создания таблицы
        if (Schema::hasTable('salary_payouts') && Schema::hasTable('salary_periods')) {
            if (! $this->foreignKeyExists('salary_payouts', 'period_id')) {
                try {
                    Schema::table('salary_payouts', function (Blueprint $table) {
                        $table->foreign('period_id')
                            ->references('id')
                            ->on('salary_periods')
                            ->cascadeOnDelete();
                    });
                } catch (Throwable $e) {
                    logger()->error('Failed to add foreign key period_id to salary_payouts: '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salary_payouts')) {
            try {
                Schema::table('salary_payouts', function (Blueprint $table) {
                    $table->dropForeign(['period_id']);
                });
            } catch (Throwable $e) {
                logger()->warning('Failed to drop foreign key from salary_payouts: '.$e->getMessage());
            }

            Schema::dropIfExists('salary_payouts');
        }
    }
};
