<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mail_thread_id');
            $table->string('direction', 20);
            $table->string('from_email');
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->string('subject');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('is_important')->default(false);
            $table->text('retention_summary')->nullable();
            $table->timestamp('content_purged_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('lead_offer_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['mail_thread_id', 'sent_at']);
            $table->index(['is_important', 'sent_at']);
            $table->index('content_purged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
