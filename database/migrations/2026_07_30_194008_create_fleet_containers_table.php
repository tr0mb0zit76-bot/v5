<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('container_number', 32);
            $table->string('size_code', 16)->nullable();
            $table->string('container_type', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['owner_contractor_id', 'container_number']);
            $table->index('container_number');
        });

        Schema::create('fleet_container_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_container_id')->constrained('fleet_containers')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_container_documents');
        Schema::dropIfExists('fleet_containers');
    }
};
