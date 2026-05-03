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
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_assistant_instructions')) {
                $table->text('trainer_assistant_instructions')->nullable()->after('training_role_mode');
            }
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_dialog_quality')) {
                $table->string('trainer_dialog_quality', 32)->nullable()->after('trainer_assistant_instructions')->index();
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
            foreach (['trainer_dialog_quality', 'trainer_assistant_instructions'] as $column) {
                if (Schema::hasColumn('sales_script_play_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
