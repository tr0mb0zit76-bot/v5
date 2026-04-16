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
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tasks', 'sla_deadline_at')) {
                $table->timestamp('sla_deadline_at')->nullable()->after('due_at');
            }

            if (! Schema::hasColumn('tasks', 'sla_escalated_at')) {
                $table->timestamp('sla_escalated_at')->nullable()->after('sla_deadline_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
                $table->dropColumn('sla_escalated_at');
            }

            if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
                $table->dropColumn('sla_deadline_at');
            }
        });
    }
};
