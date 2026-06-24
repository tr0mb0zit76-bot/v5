<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_process_stages', 'nudge_triggers')) {
                $table->json('nudge_triggers')->nullable()->after('no_reply_nudge_days');
            }

            if (! Schema::hasColumn('business_process_stages', 'ledger_idle_nudge_days')) {
                $table->unsignedSmallInteger('ledger_idle_nudge_days')->nullable()->after('nudge_triggers');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table): void {
            if (Schema::hasColumn('business_process_stages', 'ledger_idle_nudge_days')) {
                $table->dropColumn('ledger_idle_nudge_days');
            }

            if (Schema::hasColumn('business_process_stages', 'nudge_triggers')) {
                $table->dropColumn('nudge_triggers');
            }
        });
    }
};
