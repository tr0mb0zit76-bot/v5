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
        if (Schema::hasTable('leg_costs')) {
            return;
        }

        Schema::create('leg_costs', function (Blueprint $table) {
            $table->id();

            // Привязка к плечу
            $table->foreignId('order_leg_id')->constrained('order_legs')->cascadeOnDelete();

            // Финансовые данные
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('RUB');

            // Условия оплаты
            $table->string('payment_form')->nullable(); // 'no_vat', 'vat', 'mixed'
            $table->json('payment_schedule')->nullable(); // JSON с расписанием платежей

            // Статус стоимости
            $table->enum('status', ['draft', 'negotiated', 'confirmed', 'paid'])->default('draft');

            // Дата расчета и кто рассчитал
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users');

            // Связь с назначением исполнителя
            $table->foreignId('leg_contractor_assignment_id')->nullable()->constrained('leg_contractor_assignments');

            // Уникальный индекс: одна стоимость на плечо
            $table->unique('order_leg_id');

            // Индексы для быстрого поиска
            $table->index(['status', 'calculated_at']);
            $table->index(['amount']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leg_costs');
    }
};
