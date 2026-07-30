<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_rate_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('load_board_offer_id')->nullable()->constrained('load_board_offers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('carrier_name')->nullable();
            $table->decimal('rate', 14, 2);
            $table->string('currency', 3)->default('RUB');
            $table->string('payment_form')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('source', 32)->default('manual')->index();
            $table->string('status', 32)->default('received')->index();
            $table->text('comment')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_rate_quotes');
    }
};
