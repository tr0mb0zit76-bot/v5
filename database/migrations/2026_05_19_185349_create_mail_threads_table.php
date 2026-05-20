<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_threads')) {
            return;
        }

        Schema::create('mail_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('lead_offer_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'last_message_at']);
            $table->index(['order_id', 'last_message_at']);
            $table->index('last_outbound_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_threads');
    }
};
