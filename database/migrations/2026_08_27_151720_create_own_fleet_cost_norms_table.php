<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('own_fleet_cost_norms')) {
            return;
        }

        Schema::create('own_fleet_cost_norms', function (Blueprint $table) {
            $table->id();
            $table->decimal('cn_fuel_price_rub_per_liter', 12, 4)->default(0);
            $table->decimal('cn_fuel_consumption_l_per_100km', 12, 4)->default(0);
            $table->decimal('cn_driver_rub_per_km', 12, 4)->default(0);
            $table->decimal('cn_other_rub_per_km', 12, 4)->default(0);
            $table->decimal('ru_fuel_price_rub_per_liter', 12, 4)->default(0);
            $table->decimal('ru_fuel_consumption_l_per_100km', 12, 4)->default(0);
            $table->decimal('ru_driver_rub_per_km', 12, 4)->default(0);
            $table->decimal('ru_other_rub_per_km', 12, 4)->default(0);
            $table->decimal('depreciation_rub_per_km', 12, 4)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->decimal('margin_absolute_rub', 14, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_fleet_cost_norms');
    }
};
