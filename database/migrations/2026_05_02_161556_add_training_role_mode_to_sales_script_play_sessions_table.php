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
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_script_play_sessions', 'training_role_mode')) {
                $table->string('training_role_mode', 32)->default('manager_seller')->after('trainer_profile_context')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            return;
        }

        Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_script_play_sessions', 'training_role_mode')) {
                $table->dropColumn('training_role_mode');
            }
        });
    }
};
