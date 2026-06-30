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
        if (! Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::table('sales_script_trainer_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_script_trainer_messages', 'sales_script_node_id')) {
                $table->foreignId('sales_script_node_id')
                    ->nullable()
                    ->after('sales_script_play_session_id')
                    ->constrained('sales_script_nodes', indexName: 'sstm_node_id_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('sales_script_trainer_messages', 'step_key')) {
                $table->string('step_key', 120)->nullable()->after('sales_script_node_id')->index();
            }

            if (! Schema::hasColumn('sales_script_trainer_messages', 'feedback_tags')) {
                $table->json('feedback_tags')->nullable()->after('auto_peer_reaction');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::table('sales_script_trainer_messages', function (Blueprint $table) {
            if (Schema::hasColumn('sales_script_trainer_messages', 'feedback_tags')) {
                $table->dropColumn('feedback_tags');
            }

            if (Schema::hasColumn('sales_script_trainer_messages', 'step_key')) {
                $table->dropColumn('step_key');
            }

            if (Schema::hasColumn('sales_script_trainer_messages', 'sales_script_node_id')) {
                $table->dropForeign('sstm_node_id_fk');
                $table->dropColumn('sales_script_node_id');
            }
        });
    }
};
