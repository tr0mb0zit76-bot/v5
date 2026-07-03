<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('business_process_stages')) {
            return;
        }

        DB::table('leads')
            ->join('business_process_stages', 'business_process_stages.id', '=', 'leads.business_process_stage_id')
            ->where('leads.status', 'won')
            ->where('business_process_stages.is_terminal', true)
            ->where('business_process_stages.terminal_outcome', 'lost')
            ->update([
                'leads.status' => 'lost',
                'leads.updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data repair is intentionally not reversible.
    }
};
