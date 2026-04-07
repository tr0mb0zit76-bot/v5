<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Проверяет, существует ли таблица и колонка
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
        // Проверяем, существует ли уже таблица
        if (Schema::hasTable('finance_documents')) {
            return;
        }

        Schema::create('finance_documents', function (Blueprint $table) {
            $table->id();

            // Внешний ключ для order_id
            if (Schema::hasTable('orders')) {
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('order_id');
                $table->index('order_id');
            }

            $table->string('document_type', 20)->comment('invoice or upd');

            // Используем string вместо enum для лучшей совместимости
            $table->string('status', 20)->default('draft');
            $table->index('status');

            $table->string('number', 50)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_basis', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            // Внешние ключи для created_by и updated_by
            if (Schema::hasTable('users')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->index('created_by');
                $table->index('updated_by');
            }

            $table->timestamps();
            $table->softDeletes();

            // Добавляем дополнительные индексы для оптимизации
            $table->index(['order_id', 'document_type']);
            $table->index('issue_date');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Проверяем существование таблицы перед удалением
        if (Schema::hasTable('finance_documents')) {
            // Сначала удаляем внешние ключи (если они были созданы)
            try {
                Schema::table('finance_documents', function (Blueprint $table) {
                    if (Schema::hasTable('orders')) {
                        $table->dropForeign(['order_id']);
                    }
                    if (Schema::hasTable('users')) {
                        $table->dropForeign(['created_by']);
                        $table->dropForeign(['updated_by']);
                    }
                });
            } catch (Throwable $e) {
                // Логируем ошибку, но продолжаем
                logger()->warning('Failed to drop foreign keys from finance_documents: '.$e->getMessage());
            }

            // Удаляем таблицу
            Schema::dropIfExists('finance_documents');
        }
    }
};
