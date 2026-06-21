<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_script_nodes')) {
            Schema::table('sales_script_nodes', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_script_nodes', 'body_variant_b')) {
                    $table->text('body_variant_b')->nullable()->after('body');
                }
                if (! Schema::hasColumn('sales_script_nodes', 'ab_enabled')) {
                    $table->boolean('ab_enabled')->default(false)->after('body_variant_b');
                }
                if (! Schema::hasColumn('sales_script_nodes', 'ab_variant_b_weight')) {
                    $table->unsignedTinyInteger('ab_variant_b_weight')->default(50)->after('ab_enabled');
                }
            });
        }

        if (Schema::hasTable('sales_script_play_sessions')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_script_play_sessions', 'context_tags')) {
                    $table->json('context_tags')->nullable()->after('order_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_script_nodes')) {
            Schema::table('sales_script_nodes', function (Blueprint $table): void {
                foreach (['body_variant_b', 'ab_enabled', 'ab_variant_b_weight'] as $column) {
                    if (Schema::hasColumn('sales_script_nodes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sales_script_play_sessions')) {
            Schema::table('sales_script_play_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('sales_script_play_sessions', 'context_tags')) {
                    $table->dropColumn('context_tags');
                }
            });
        }
    }
};
