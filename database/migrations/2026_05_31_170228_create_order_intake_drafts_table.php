<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_intake_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_original_name');
            $table->string('source_mime_type', 120)->nullable();
            $table->string('source_storage_path')->nullable();
            $table->string('source_storage_driver', 32)->nullable();
            $table->string('source_text_hash', 64)->nullable();
            $table->unsignedInteger('source_text_length')->default(0);
            $table->string('model', 80)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('extracted_payload')->nullable();
            $table->json('wizard_patch')->nullable();
            $table->json('warnings')->nullable();
            $table->json('matched_contractors')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_intake_drafts');
    }
};
