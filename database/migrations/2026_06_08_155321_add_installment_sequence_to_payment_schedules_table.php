<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        if (! Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            Schema::table('payment_schedules', function (Blueprint $table): void {
                $table->unsignedTinyInteger('installment_sequence')->nullable()->after('type');
            });
        }

        if (Schema::hasColumn('payment_schedules', 'type')) {
            DB::statement("ALTER TABLE payment_schedules MODIFY COLUMN type ENUM('prepayment', 'final', 'installment') NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        if (Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            Schema::table('payment_schedules', function (Blueprint $table): void {
                $table->dropColumn('installment_sequence');
            });
        }

        if (Schema::hasColumn('payment_schedules', 'type')) {
            DB::statement("ALTER TABLE payment_schedules MODIFY COLUMN type ENUM('prepayment', 'final') NOT NULL");
        }
    }
};
