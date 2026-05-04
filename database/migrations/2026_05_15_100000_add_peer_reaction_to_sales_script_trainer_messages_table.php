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
            if (! Schema::hasColumn('sales_script_trainer_messages', 'peer_reaction')) {
                $table->string('peer_reaction', 20)->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::table('sales_script_trainer_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_script_trainer_messages', 'peer_reaction')) {
                $table->dropColumn('peer_reaction');
            }
        });
    }
};
