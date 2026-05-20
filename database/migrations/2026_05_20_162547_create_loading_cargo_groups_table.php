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

        if (Schema::hasTable('loading_cargo_groups')) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loading_cargo_groups');
    }
};
