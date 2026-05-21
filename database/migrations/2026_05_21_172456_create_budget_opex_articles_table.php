<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_opex_articles')) {
            return;
        }

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedTinyInteger('ramp_months')->nullable()->comment('Только первые N месяцев; null — каждый месяц');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_opex_articles');
    }
};
