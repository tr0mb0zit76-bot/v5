<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_document_edo_acknowledgements')) {
            return;
        }

        Schema::create('order_document_edo_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('party', 32);
            $table->string('document_type', 64);
            $table->string('slot_key', 128)->default('');
            $table->unsignedBigInteger('contractor_id')->default(0);
            $table->boolean('received_via_edo')->default(false);
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_id', 'party', 'document_type', 'slot_key', 'contractor_id'],
                'order_document_edo_ack_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_document_edo_acknowledgements');
    }
};
