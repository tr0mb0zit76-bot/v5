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
            if (! Schema::hasColumn('business_process_stages', 'auto_create_task')) {
                $table->boolean('auto_create_task')->default(false)->after('terminal_outcome');
            }
            if (! Schema::hasColumn('business_process_stages', 'task_title_template')) {
                $table->string('task_title_template')->nullable()->after('auto_create_task');
            }
            if (! Schema::hasColumn('business_process_stages', 'task_description_template')) {
                $table->text('task_description_template')->nullable()->after('task_title_template');
            }
            if (! Schema::hasColumn('business_process_stages', 'task_due_days_offset')) {
                $table->unsignedSmallInteger('task_due_days_offset')->default(0)->after('task_description_template');
            }
            if (! Schema::hasColumn('business_process_stages', 'task_priority')) {
                $table->string('task_priority', 20)->default('medium')->after('task_due_days_offset');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            foreach ([
                'task_priority',
                'task_due_days_offset',
                'task_description_template',
                'task_title_template',
                'auto_create_task',
            ] as $column) {
                if (Schema::hasColumn('business_process_stages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
