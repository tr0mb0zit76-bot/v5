<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loading_planner_projects')) {
            return;
        }

        Schema::table('loading_planner_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('loading_planner_projects', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('user_id');
                $table->index('lead_id');
            }

            if (! Schema::hasColumn('loading_planner_projects', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('lead_id');
                $table->index('order_id');
            }
        });

        if (Schema::hasTable('leads') && Schema::hasColumn('loading_planner_projects', 'lead_id')) {
            Schema::table('loading_planner_projects', function (Blueprint $table): void {
                $table->foreign('lead_id')
                    ->references('id')
                    ->on('leads')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('loading_planner_projects', 'order_id')) {
            Schema::table('loading_planner_projects', function (Blueprint $table): void {
                $table->foreign('order_id')
                    ->references('id')
                    ->on('orders')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('loading_planner_projects')) {
            return;
        }

        Schema::table('loading_planner_projects', function (Blueprint $table): void {
            if (Schema::hasColumn('loading_planner_projects', 'order_id')) {
                try {
                    $table->dropForeign(['order_id']);
                } catch (Throwable) {
                }
                $table->dropColumn('order_id');
            }

            if (Schema::hasColumn('loading_planner_projects', 'lead_id')) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable) {
                }
                $table->dropColumn('lead_id');
            }
        });
    }
};
