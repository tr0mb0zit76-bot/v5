<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            if (! Schema::hasColumn('business_process_stages', 'stage_goal')) {
                $table->string('stage_goal', 500)->nullable()->after('description');
            }

            if (! Schema::hasColumn('business_process_stages', 'success_criteria')) {
                $table->text('success_criteria')->nullable()->after('stage_goal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            foreach (['success_criteria', 'stage_goal'] as $column) {
                if (Schema::hasColumn('business_process_stages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
