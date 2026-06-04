<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_intake_phrase_learnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field', 32);
            $table->string('source_phrase', 255);
            $table->string('canonical_value', 255);
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'field', 'source_phrase'], 'order_intake_phrase_learnings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_intake_phrase_learnings');
    }
};
