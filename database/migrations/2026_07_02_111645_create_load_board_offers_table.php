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
        Schema::create('load_board_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_board_post_id')->constrained('load_board_posts')->cascadeOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('proposed')->index();
            $table->decimal('carrier_rate', 14, 2);
            $table->string('carrier_rate_currency', 3)->default('RUB');
            $table->string('payment_form')->nullable();
            $table->date('available_date')->nullable();
            $table->string('carrier_contact')->nullable();
            $table->text('conditions')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['load_board_post_id', 'status']);
            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_board_offers');
    }
};
