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
        Schema::table('contractors', function (Blueprint $table): void {
            $table->string('work_status', 32)
                ->default('in_development')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            $table->string('work_status', 32)
                ->default('active')
                ->change();
        });
    }
};
