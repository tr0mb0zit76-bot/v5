<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_schedule_payment_events')) {
            return;
        }

        Schema::table('payment_schedule_payment_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('recorded_by');
            }

            if (! Schema::hasColumn('payment_schedule_payment_events', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_schedule_payment_events')) {
            return;
        }

        Schema::table('payment_schedule_payment_events', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_by')) {
                $table->dropColumn('reversed_by');
            }

            if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
        });
    }
};
