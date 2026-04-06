<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_cargo_items')) {
            return;
        }

        Schema::create('lead_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 2)->nullable();
            $table->string('package_type', 50)->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->boolean('dangerous_goods')->default(false);
            $table->string('dangerous_class', 10)->nullable();
            $table->string('hs_code', 50)->nullable();
            $table->string('cargo_type', 50)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_cargo_items');
    }
};
