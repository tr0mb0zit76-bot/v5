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

        if (Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $placeAfterAmount = in_array($driver, ['mysql', 'mariadb'], true)
            && Schema::hasColumn('payment_schedules', 'amount');

        Schema::table('payment_schedules', function (Blueprint $table) use ($placeAfterAmount) {
            if ($placeAfterAmount) {
                $table->string('invoice_number', 120)->nullable()->after('amount');
            } else {
                $table->string('invoice_number', 120)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
    }
};
