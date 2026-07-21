<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('linked_order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('link_type', 32)->default('expedition_chain');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'linked_order_id']);
            $table->index('linked_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_links');
    }
};
