<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lead_cargo_items')) {
            return;
        }

        Schema::table('lead_cargo_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_cargo_items', 'metadata')) {
                $table->json('metadata')->nullable()->after('cargo_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_cargo_items')) {
            return;
        }

        Schema::table('lead_cargo_items', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_cargo_items', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
