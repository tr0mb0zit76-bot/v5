<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_numbering_rules', function (Blueprint $table) {
            $table->id();
            $table->string('cipher', 32)->unique();
            $table->foreignId('own_company_id')->unique()->constrained('contractors')->cascadeOnDelete();
            $table->string('separator', 3)->default('-');
            $table->string('prefix_type', 16)->default('sequence');
            $table->string('prefix_value', 64)->nullable();
            $table->string('body_type', 16)->default('text');
            $table->string('body_value', 64)->nullable();
            $table->string('suffix_type', 16)->default('month');
            $table->string('suffix_value', 64)->nullable();
            $table->unsignedTinyInteger('sequence_pad')->default(0);
            $table->string('sequence_scope', 16)->default('month');
            $table->json('sequence_counters')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_numbering_rules');
    }
};
