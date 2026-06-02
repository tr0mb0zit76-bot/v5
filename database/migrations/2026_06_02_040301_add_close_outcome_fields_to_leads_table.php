<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'close_outcome_primary_flag')) {
                $table->string('close_outcome_primary_flag', 64)->nullable()->after('lost_reason');
            }
            if (! Schema::hasColumn('leads', 'close_outcome_secondary_flags')) {
                $table->json('close_outcome_secondary_flags')->nullable()->after('close_outcome_primary_flag');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (Schema::hasColumn('leads', 'close_outcome_secondary_flags')) {
                $table->dropColumn('close_outcome_secondary_flags');
            }
            if (Schema::hasColumn('leads', 'close_outcome_primary_flag')) {
                $table->dropColumn('close_outcome_primary_flag');
            }
        });
    }
};
