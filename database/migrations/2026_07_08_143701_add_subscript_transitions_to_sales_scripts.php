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
        if (Schema::hasTable('sales_script_transitions')) {
            Schema::table('sales_script_transitions', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_script_transitions', 'target_type')) {
                    $table->string('target_type', 32)->default('node')->after('to_node_id')->index();
                }

                if (! Schema::hasColumn('sales_script_transitions', 'target_sales_script_version_id')) {
                    $table->foreignId('target_sales_script_version_id')
                        ->nullable()
                        ->after('target_type')
                        ->constrained('sales_script_versions')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('sales_script_play_sessions')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_script_play_sessions', 'return_stack')) {
                    $table->json('return_stack')->nullable()->after('context_tags');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_script_transitions')) {
            Schema::table('sales_script_transitions', function (Blueprint $table): void {
                if (Schema::hasColumn('sales_script_transitions', 'target_sales_script_version_id')) {
                    $table->dropForeign(['target_sales_script_version_id']);
                    $table->dropColumn('target_sales_script_version_id');
                }

                if (Schema::hasColumn('sales_script_transitions', 'target_type')) {
                    $table->dropColumn('target_type');
                }
            });
        }

        if (Schema::hasTable('sales_script_play_sessions')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('sales_script_play_sessions', 'return_stack')) {
                    $table->dropColumn('return_stack');
                }
            });
        }
    }
};
