<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_signing_own_company')) {
            return;
        }

        Schema::create('user_signing_own_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'contractor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_signing_own_company');
    }
};
