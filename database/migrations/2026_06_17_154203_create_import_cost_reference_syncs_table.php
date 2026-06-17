<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_cost_reference_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('status', 32);
            $table->unsignedInteger('items_updated')->default(0);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->index(['source', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_cost_reference_syncs');
    }
};
