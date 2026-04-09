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
        if (Schema::hasTable('task_attachments')) {
            return;
        }

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
