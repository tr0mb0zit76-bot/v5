<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 80);
            $table->json('arguments')->nullable();
            $table->boolean('ok')->default(true);
            $table->string('error_message', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['tool', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_audit_logs');
    }
};
