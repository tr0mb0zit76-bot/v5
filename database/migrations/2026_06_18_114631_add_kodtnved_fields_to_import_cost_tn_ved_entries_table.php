<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_cost_tn_ved_entries', function (Blueprint $table): void {
            $table->json('kodtnved_payload')->nullable()->after('eec_synced_at');
            $table->timestamp('kodtnved_synced_at')->nullable()->after('kodtnved_payload');
        });
    }

    public function down(): void
    {
        Schema::table('import_cost_tn_ved_entries', function (Blueprint $table): void {
            $table->dropColumn(['kodtnved_payload', 'kodtnved_synced_at']);
        });
    }
};
