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
            if (! Schema::hasColumn('payment_schedules', 'payment_run_date')) {
                $table->date('payment_run_date')->nullable()->after('actual_date')->index();
            }

            if (! Schema::hasColumn('payment_schedules', 'payment_run_by')) {
                $table->foreignId('payment_run_by')
                    ->nullable()
                    ->after('payment_run_date')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_schedules', 'payment_run_note')) {
                $table->string('payment_run_note', 500)->nullable()->after('payment_run_by');
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
            if (Schema::hasColumn('payment_schedules', 'payment_run_by')) {
                $table->dropConstrainedForeignId('payment_run_by');
            }

            foreach (['payment_run_note', 'payment_run_date'] as $column) {
                if (Schema::hasColumn('payment_schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
