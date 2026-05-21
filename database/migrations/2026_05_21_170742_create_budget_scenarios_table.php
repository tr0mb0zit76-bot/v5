<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_scenarios')) {
            return;
        }

        Schema::create('budget_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Основной');
            $table->json('inputs');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_scenarios');
    }
};
