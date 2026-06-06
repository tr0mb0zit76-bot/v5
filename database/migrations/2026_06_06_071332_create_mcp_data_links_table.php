<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_data_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64);
            $table->string('target_key', 64);
            $table->boolean('bidirectional')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('label', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'target_key'], 'mcp_data_links_pair_unique');
            $table->index(['source_key', 'is_active']);
            $table->index(['target_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_data_links');
    }
};
