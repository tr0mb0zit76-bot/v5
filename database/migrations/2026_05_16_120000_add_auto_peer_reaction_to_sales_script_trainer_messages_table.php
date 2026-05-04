<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::table('sales_script_trainer_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_script_trainer_messages', 'auto_peer_reaction')) {
                $table->string('auto_peer_reaction', 20)->nullable()->after('peer_reaction');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::table('sales_script_trainer_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_script_trainer_messages', 'auto_peer_reaction')) {
                $table->dropColumn('auto_peer_reaction');
            }
        });
    }
};
