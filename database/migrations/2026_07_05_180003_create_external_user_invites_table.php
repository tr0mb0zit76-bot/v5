<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_user_invites')) {
            return;
        }

        Schema::create('external_user_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_contact_id')->constrained('contractor_contacts')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('external_party', 16);
            $table->char('token_hash', 64);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['token_hash', 'revoked_at'], 'eui_token_revoked_idx');
            $table->index('expires_at', 'eui_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_user_invites');
    }
};
