<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_script_transitions')) {
            if (! Schema::hasColumn('sales_script_transitions', 'conversation_effect')) {
                Schema::table('sales_script_transitions', function (Blueprint $table): void {
                    $table->string('conversation_effect', 20)->nullable()->after('customer_label');
                });
            }
            if (! Schema::hasColumn('sales_script_transitions', 'momentum_delta')) {
                Schema::table('sales_script_transitions', function (Blueprint $table): void {
                    $table->smallInteger('momentum_delta')->nullable()->after('conversation_effect');
                });
            }
            if (! Schema::hasColumn('sales_script_transitions', 'next_move_preview')) {
                Schema::table('sales_script_transitions', function (Blueprint $table): void {
                    $table->string('next_move_preview', 500)->nullable()->after('momentum_delta');
                });
            }
        }

        if (Schema::hasTable('sales_script_play_sessions')
            && ! Schema::hasColumn('sales_script_play_sessions', 'crm_synced_at')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                $table->timestamp('crm_synced_at')->nullable()->after('completed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_script_play_sessions')
            && Schema::hasColumn('sales_script_play_sessions', 'crm_synced_at')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                $table->dropColumn('crm_synced_at');
            });
        }

        foreach (['next_move_preview', 'momentum_delta', 'conversation_effect'] as $column) {
            if (Schema::hasTable('sales_script_transitions')
                && Schema::hasColumn('sales_script_transitions', $column)) {
                Schema::table('sales_script_transitions', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
