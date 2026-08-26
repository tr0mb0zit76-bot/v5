<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_schedules', 'payment_form')) {
                $table->string('payment_form', 50)->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_schedules', 'payment_form')) {
                $table->dropColumn('payment_form');
            }
        });
    }
};
