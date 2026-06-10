<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_reconcile_rules')) {
            return;
        }

        Schema::create('management_reconcile_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('keyword', 128);
            $table->string('direction', 8)->nullable();
            $table->string('allocation_type', 16);
            $table->foreignId('category_id')->nullable()->constrained('management_expense_categories')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 32)->nullable();
            $table->foreignId('payment_schedule_id')
                ->nullable()
                ->constrained('payment_schedules')
                ->nullOnDelete();
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('times_applied')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_reconcile_rules');
    }
};
