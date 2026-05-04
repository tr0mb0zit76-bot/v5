<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_ai_role')) {
                $table->string('trainer_ai_role', 20)->nullable()->after('trainer_score');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_script_play_sessions', 'trainer_ai_role')) {
                $table->dropColumn('trainer_ai_role');
            }
        });
    }
};
