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
        if (! Schema::hasTable('sales_script_play_sessions') || Schema::hasColumn('sales_script_play_sessions', 'lead_id')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('lead_id')->nullable()->after('contractor_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions') || ! Schema::hasColumn('sales_script_play_sessions', 'lead_id')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            $table->dropColumn('lead_id');
        });
    }
};
