<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_cost_tn_ved_entries', function (Blueprint $table): void {
            $table->json('alta_payload')->nullable()->after('kodtnved_synced_at');
            $table->timestamp('alta_synced_at')->nullable()->after('alta_payload');
        });
    }

    public function down(): void
    {
        Schema::table('import_cost_tn_ved_entries', function (Blueprint $table): void {
            $table->dropColumn(['alta_payload', 'alta_synced_at']);
        });
    }
};
