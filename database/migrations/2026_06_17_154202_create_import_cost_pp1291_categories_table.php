<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_cost_pp1291_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->unsignedInteger('base_fee_rub')->default(150_000);
            $table->json('age_coefficients');
            $table->string('decree_reference', 120)->default('ПП РФ № 1291');
            $table->date('effective_from')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_cost_pp1291_categories');
    }
};
