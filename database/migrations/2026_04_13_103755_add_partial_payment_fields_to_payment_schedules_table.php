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
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
            }

            if (! Schema::hasColumn('payment_schedules', 'remaining_amount')) {
                $table->decimal('remaining_amount', 12, 2)->default(0)->after('paid_amount');
            }

            if (! Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
                $table->foreignId('parent_payment_id')
                    ->nullable()
                    ->constrained('payment_schedules')
                    ->nullOnDelete()
                    ->after('counterparty_id');
            }

            if (! Schema::hasColumn('payment_schedules', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('remaining_amount');
            }

            if (! Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $table->string('transaction_reference', 100)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('payment_schedules', 'is_partial')) {
                $table->boolean('is_partial')->default(false)->after('transaction_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }

            if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
                $table->dropColumn('remaining_amount');
            }

            if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
                $table->dropForeign(['parent_payment_id']);
                $table->dropColumn('parent_payment_id');
            }

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $table->dropColumn('transaction_reference');
            }

            if (Schema::hasColumn('payment_schedules', 'is_partial')) {
                $table->dropColumn('is_partial');
            }
        });
    }
};
