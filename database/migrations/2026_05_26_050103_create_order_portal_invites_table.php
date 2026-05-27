<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_portal_invites')) {
            return;
        }

        Schema::create('order_portal_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->unsignedTinyInteger('carrier_slot')->default(1);
            $table->string('purpose', 32)->default('carrier_fleet');
            $table->char('token_hash', 64);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->json('submitted_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'contractor_id', 'stage', 'carrier_slot'], 'opi_order_party_stage_slot_idx');
            $table->index(['token_hash', 'revoked_at'], 'opi_token_revoked_idx');
            $table->index('expires_at', 'opi_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_portal_invites');
    }
};
