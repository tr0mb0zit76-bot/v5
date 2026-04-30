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
            if (! Schema::hasColumn('sales_script_play_sessions', 'is_trainer')) {
                $table->boolean('is_trainer')->default(false)->after('order_id')->index();
            }
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_profile_key')) {
                $table->string('trainer_profile_key', 100)->nullable()->after('is_trainer');
            }
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_profile_title')) {
                $table->string('trainer_profile_title', 160)->nullable()->after('trainer_profile_key');
            }
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_profile_context')) {
                $table->text('trainer_profile_context')->nullable()->after('trainer_profile_title');
            }
            if (! Schema::hasColumn('sales_script_play_sessions', 'trainer_score')) {
                $table->unsignedTinyInteger('trainer_score')->nullable()->after('trainer_profile_context')->index();
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
            foreach ([
                'trainer_score',
                'trainer_profile_context',
                'trainer_profile_title',
                'trainer_profile_key',
                'is_trainer',
            ] as $column) {
                if (Schema::hasColumn('sales_script_play_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
