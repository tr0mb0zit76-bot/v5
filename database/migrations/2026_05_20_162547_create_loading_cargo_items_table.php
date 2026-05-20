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
        if (! Schema::hasTable('loading_planner_projects')) {
            Schema::create('loading_planner_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('selected_transport_template_id')->nullable();
                $table->string('name');
                $table->string('status', 40)->default('draft');
                $table->json('calculation')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('loading_cargo_groups')) {
            Schema::create('loading_cargo_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loading_planner_project_id')->constrained()->cascadeOnDelete();
                $table->string('name')->default('Грузовая группа #1');
                $table->string('recipient_name')->nullable();
                $table->string('color', 20)->default('#60a5fa');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['loading_planner_project_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('loading_cargo_items')) {
            return;
        }

        Schema::create('loading_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loading_cargo_group_id')->constrained()->cascadeOnDelete();
            $table->string('client_key', 80)->nullable()->index();
            $table->string('name');
            $table->string('package_type', 40)->default('box');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('length_mm')->default(1200);
            $table->unsignedInteger('width_mm')->default(800);
            $table->unsignedInteger('height_mm')->default(1000);
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->boolean('can_rotate')->default(true);
            $table->boolean('stackable')->default(false);
            $table->unsignedTinyInteger('max_stack')->default(1);
            $table->boolean('can_tilt')->default(false);
            $table->string('color', 20)->default('#93c5fd');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['loading_cargo_group_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loading_cargo_items');
    }
};
