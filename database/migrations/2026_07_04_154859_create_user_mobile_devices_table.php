<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_key', 64)->unique();
            $table->string('pin_hash');
            $table->string('device_name')->nullable();
            $table->text('fcm_token')->nullable();
            $table->unsignedTinyInteger('failed_pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_mobile_devices');
    }
};
