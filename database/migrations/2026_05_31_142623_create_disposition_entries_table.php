<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->date('date');
            $table->string('slot', 16);
            $table->string('location', 500)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'date', 'slot']);
            $table->index(['date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_entries');
    }
};
