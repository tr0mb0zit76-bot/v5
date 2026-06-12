<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grid_views')) {
            return;
        }

        Schema::create('grid_views', function (Blueprint $table) {
            $table->id();
            $table->string('grid_key', 64);
            $table->string('name');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('visibility', 32)->default('private');
            $table->json('shared_with')->nullable();
            $table->json('column_state')->nullable();
            $table->json('filter_state')->nullable();
            $table->json('sort_state')->nullable();
            $table->string('quick_search', 500)->nullable();
            $table->boolean('is_pinned_sidebar')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['grid_key', 'owner_user_id']);
            $table->index(['is_pinned_sidebar', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grid_views');
    }
};
