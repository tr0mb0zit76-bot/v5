<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_one_c_documents')) {
            return;
        }

        Schema::create('order_one_c_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('status', 32);
            $table->uuid('external_ref')->nullable();
            $table->string('external_number', 64)->nullable();
            $table->dateTime('external_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('counterparty_inn', 12)->nullable();
            $table->string('counterparty_kpp', 9)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'document_type']);
            $table->index(['document_type', 'status']);
            $table->index('external_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_one_c_documents');
    }
};
