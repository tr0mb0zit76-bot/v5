<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_route_points')) {
            return;
        }

        Schema::create('lead_route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address', 500);
            $table->json('normalized_data')->nullable();
            $table->date('planned_date')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_route_points');
    }
};
