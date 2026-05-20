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

        Schema::table('business_process_stages', function (Blueprint $table) {
            if (! Schema::hasColumn('business_process_stages', 'no_reply_nudge_days')) {
                $table->unsignedSmallInteger('no_reply_nudge_days')->nullable()->after('task_priority');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            if (Schema::hasColumn('business_process_stages', 'no_reply_nudge_days')) {
                $table->dropColumn('no_reply_nudge_days');
            }
        });
    }
};
