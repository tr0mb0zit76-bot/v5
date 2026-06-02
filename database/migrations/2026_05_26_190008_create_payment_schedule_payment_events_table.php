<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePaymentSchedulesTable();

        if (Schema::hasTable('payment_schedule_payment_events')) {
            return;
        }

        Schema::create('payment_schedule_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained('payment_schedules')->nullOnDelete();
            $table->string('party', 16);
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'payment_date'], 'pspe_contractor_date_idx');
            $table->index(['order_id', 'party'], 'pspe_order_party_idx');
            $table->index('payment_schedule_id', 'pspe_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_payment_events');
    }

    /**
     * Таблица создаётся в schema dump; при migrate без dump её не было, а ALTER-миграции уже «прошли» с пропуском.
     */
    private function ensurePaymentSchedulesTable(): void
    {
        if (Schema::hasTable('payment_schedules')) {
            return;
        }

        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('party', ['customer', 'carrier', 'contractor']);
            $table->enum('type', ['prepayment', 'final']);
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('invoice_number', 120)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->boolean('is_partial')->default(false);
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreignId('counterparty_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('parent_payment_id')->nullable()->constrained('payment_schedules')->nullOnDelete();

            $table->index(['order_id', 'party', 'type'], 'payment_schedules_order_id_party_type_index');
            $table->index('status');
        });
    }
};
