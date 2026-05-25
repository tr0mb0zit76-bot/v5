<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_trips')) {
            return;
        }

        Schema::create('fleet_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('order_leg_stage', 50);
            $table->unsignedTinyInteger('carrier_slot')->nullable();
            $table->foreignId('fleet_vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete();
            $table->foreignId('fleet_driver_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->string('status', 32)->default('planned');
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->unsignedInteger('planned_km')->nullable();
            $table->unsignedInteger('actual_km')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'order_leg_stage', 'carrier_slot'], 'fleet_trips_order_leg_slot_unique');
            $table->index('status');
        });

        Schema::create('fleet_trip_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_trip_id')->constrained('fleet_trips')->cascadeOnDelete();
            $table->string('cost_category', 32);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('RUB');
            $table->text('comment')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index('cost_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trip_cost_lines');
        Schema::dropIfExists('fleet_trips');
    }
};
