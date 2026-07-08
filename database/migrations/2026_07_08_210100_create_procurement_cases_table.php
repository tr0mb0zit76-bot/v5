<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('load_board_post_id')
                ->nullable()
                ->constrained('load_board_posts')
                ->nullOnDelete();
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('order_owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('dispatcher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('buying_own_company_id')
                ->nullable()
                ->constrained('contractors')
                ->nullOnDelete();
            $table->string('status', 32)->default('new');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_cases');
    }
};
