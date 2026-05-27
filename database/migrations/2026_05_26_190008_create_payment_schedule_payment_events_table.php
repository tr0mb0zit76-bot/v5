<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
};
