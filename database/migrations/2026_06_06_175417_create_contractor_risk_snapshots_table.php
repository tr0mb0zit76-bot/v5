<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('inn', 12);
            $table->string('model_version', 16);
            $table->json('normalized_data');
            $table->json('scoring_result');
            $table->boolean('checko_from_cache')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_risk_snapshots');
    }
};
