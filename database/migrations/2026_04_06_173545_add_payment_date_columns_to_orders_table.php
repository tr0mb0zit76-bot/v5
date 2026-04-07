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
     * Run the migrations.
     */
    public function up(): void
    {
        // Проверяем существование таблицы orders
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Добавляем customer_payment_date после customer_payment_term
            if (! $this->hasColumn('orders', 'customer_payment_date')) {
                if ($this->hasColumn('orders', 'customer_payment_term')) {
                    $table->date('customer_payment_date')->nullable()->after('customer_payment_term');
                } else {
                    $table->date('customer_payment_date')->nullable();
                }
            }

            // Добавляем carrier_payment_date после carrier_payment_term
            if (! $this->hasColumn('orders', 'carrier_payment_date')) {
                if ($this->hasColumn('orders', 'carrier_payment_term')) {
                    $table->date('carrier_payment_date')->nullable()->after('carrier_payment_term');
                } else {
                    $table->date('carrier_payment_date')->nullable();
                }
            }

            // Добавляем additional_expenses_payment_date после additional_expenses
            if (! $this->hasColumn('orders', 'additional_expenses_payment_date')) {
                if ($this->hasColumn('orders', 'additional_expenses')) {
                    $table->date('additional_expenses_payment_date')->nullable()->after('additional_expenses');
                } else {
                    $table->date('additional_expenses_payment_date')->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Проверяем существование таблицы orders
        if (! Schema::hasTable('orders')) {
            return;
        }

        // Собираем только те колонки, которые существуют
        $columnsToDrop = [];

        if ($this->hasColumn('orders', 'customer_payment_date')) {
            $columnsToDrop[] = 'customer_payment_date';
        }

        if ($this->hasColumn('orders', 'carrier_payment_date')) {
            $columnsToDrop[] = 'carrier_payment_date';
        }

        if ($this->hasColumn('orders', 'additional_expenses_payment_date')) {
            $columnsToDrop[] = 'additional_expenses_payment_date';
        }

        // Удаляем колонки, если они есть
        if (! empty($columnsToDrop)) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop) {
                try {
                    $table->dropColumn($columnsToDrop);
                } catch (Throwable $e) {
                    logger()->warning('Failed to drop columns from orders: '.$e->getMessage());
                }
            });
        }
    }
};
