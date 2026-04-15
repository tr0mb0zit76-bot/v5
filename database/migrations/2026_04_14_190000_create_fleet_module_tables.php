<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('tractor_brand', 120)->nullable();
            $table->string('trailer_brand', 120)->nullable();
            $table->string('tractor_plate', 32)->nullable();
            $table->string('trailer_plate', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fleet_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('full_name', 255);
            $table->string('passport_series', 16)->nullable();
            $table->string('passport_number', 32)->nullable();
            $table->string('passport_issued_by', 500)->nullable();
            $table->date('passport_issued_at')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('license_number', 64)->nullable();
            $table->string('license_categories', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_driver_id')->constrained('fleet_drivers')->cascadeOnDelete();
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
        Schema::dropIfExists('fleet_driver_documents');
        Schema::dropIfExists('fleet_drivers');
        Schema::dropIfExists('fleet_vehicle_documents');
        Schema::dropIfExists('fleet_vehicles');
    }
};
