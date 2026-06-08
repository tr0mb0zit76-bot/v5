<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'accounting_handoff_at')) {
                $table->timestamp('accounting_handoff_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('orders', 'accounting_handoff_by')) {
                $table->foreignId('accounting_handoff_by')->nullable()->after('accounting_handoff_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'accounting_handoff_by')) {
                $table->dropConstrainedForeignId('accounting_handoff_by');
            }
            if (Schema::hasColumn('orders', 'accounting_handoff_at')) {
                $table->dropColumn('accounting_handoff_at');
            }
        });
    }
};
