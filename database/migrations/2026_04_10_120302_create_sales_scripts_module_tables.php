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
        if (Schema::hasTable('sales_scripts')) {
            return;
        }

        Schema::create('sales_script_reaction_classes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_scripts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('channel')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_script_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_script_id')->constrained('sales_scripts')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('entry_node_key')->nullable();
            $table->timestamps();

            $table->unique(['sales_script_id', 'version_number']);
        });

        Schema::create('sales_script_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_script_version_id')->constrained('sales_script_versions')->cascadeOnDelete();
            $table->string('client_key')->nullable();
            $table->string('kind', 32);
            $table->text('body');
            $table->text('hint')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sales_script_version_id', 'client_key']);
        });

        Schema::create('sales_script_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_script_version_id')->constrained('sales_script_versions')->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('sales_script_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('sales_script_nodes')->cascadeOnDelete();
            $table->foreignId('sales_script_reaction_class_id')->nullable()->constrained('sales_script_reaction_classes')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_script_play_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_script_version_id')->constrained('sales_script_versions')->cascadeOnDelete();
            $table->foreignId('current_node_id')->nullable()->constrained('sales_script_nodes')->nullOnDelete();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->foreignId('primary_reaction_class_id')->nullable()->constrained('sales_script_reaction_classes')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_script_play_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_script_play_session_id')->constrained('sales_script_play_sessions')->cascadeOnDelete();
            $table->string('type', 32);
            $table->foreignId('sales_script_node_id')->nullable()->constrained('sales_script_nodes')->nullOnDelete();
            $table->foreignId('sales_script_reaction_class_id')->nullable()->constrained('sales_script_reaction_classes')->nullOnDelete();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_script_play_events');
        Schema::dropIfExists('sales_script_play_sessions');
        Schema::dropIfExists('sales_script_transitions');
        Schema::dropIfExists('sales_script_nodes');
        Schema::dropIfExists('sales_script_versions');
        Schema::dropIfExists('sales_scripts');
        Schema::dropIfExists('sales_script_reaction_classes');
    }
};
