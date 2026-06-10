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
        if (! Schema::hasTable('sales_script_capture_fields')) {
            Schema::create('sales_script_capture_fields', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('label');
                $table->string('value_type', 32)->default('text');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sales_script_play_session_field_values')) {
            Schema::create('sales_script_play_session_field_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_script_play_session_id')
                    ->constrained('sales_script_play_sessions', indexName: 'sspsfv_session_id_fk')
                    ->cascadeOnDelete();
                $table->foreignId('sales_script_capture_field_id')
                    ->constrained('sales_script_capture_fields', indexName: 'sspsfv_capture_field_fk')
                    ->cascadeOnDelete();
                $table->text('value');
                $table->foreignId('captured_at_node_id')
                    ->nullable()
                    ->constrained('sales_script_nodes', indexName: 'sspsfv_captured_node_fk')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['sales_script_play_session_id', 'sales_script_capture_field_id'],
                    'sspsfv_session_field_unique',
                );
            });
        }

        if (! Schema::hasTable('sales_script_node_templates')) {
            Schema::create('sales_script_node_templates', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('kind', 32);
                $table->text('body');
                $table->text('hint')->nullable();
                $table->json('tags')->nullable();
                $table->json('capture_field_codes')->nullable();
                $table->json('default_transitions')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('sales_script_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_script_nodes', 'capture_field_codes')) {
                $table->json('capture_field_codes')->nullable()->after('tags');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_script_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('sales_script_nodes', 'capture_field_codes')) {
                $table->dropColumn('capture_field_codes');
            }
        });

        Schema::dropIfExists('sales_script_node_templates');
        Schema::dropIfExists('sales_script_play_session_field_values');
        Schema::dropIfExists('sales_script_capture_fields');
    }
};
