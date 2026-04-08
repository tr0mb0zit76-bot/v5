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
        Schema::create('leg_contractor_assignments', function (Blueprint $table) {
            $table->id();

            // Привязка к плечу
            $table->foreignId('order_leg_id')->constrained('order_legs')->cascadeOnDelete();

            // Исполнитель (перевозчик)
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();

            // Дата назначения и кто назначил
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->constrained('users');

            // Статус назначения
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');

            // Комментарий к назначению
            $table->text('notes')->nullable();

            // Уникальный индекс: одно назначение на плечо
            $table->unique('order_leg_id');

            // Индексы для быстрого поиска
            $table->index(['contractor_id', 'status']);
            $table->index(['assigned_at']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leg_contractor_assignments');
    }
};
