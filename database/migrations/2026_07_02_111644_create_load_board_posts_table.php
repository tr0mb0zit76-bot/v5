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
        Schema::create('load_board_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 32)->default('normal')->index();
            $table->string('title');
            $table->string('loading_location')->nullable();
            $table->string('unloading_location')->nullable();
            $table->date('loading_date')->nullable();
            $table->date('unloading_date')->nullable();
            $table->string('cargo_name')->nullable();
            $table->decimal('cargo_weight', 10, 2)->nullable();
            $table->decimal('cargo_volume', 10, 2)->nullable();
            $table->string('transport_type')->nullable();
            $table->decimal('customer_rate', 14, 2)->nullable();
            $table->string('customer_rate_currency', 3)->default('RUB');
            $table->decimal('target_carrier_rate', 14, 2)->nullable();
            $table->string('payment_form')->nullable();
            $table->text('requirements')->nullable();
            $table->text('seller_comment')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['loading_date', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_board_posts');
    }
};
