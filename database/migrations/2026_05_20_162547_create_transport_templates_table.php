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
        if (Schema::hasTable('transport_templates')) {
            return;
        }

        Schema::create('transport_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category', 40)->default('truck');
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->unsignedInteger('max_payload_kg')->default(0);
            $table->unsignedTinyInteger('axles_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_templates');
    }
};
