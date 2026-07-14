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
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('recipient_user_id')
                ->constrained('chat_messages')
                ->nullOnDelete();
        });

        Schema::create('chat_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')
                ->constrained('chat_messages')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_attachments');

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reply_to_message_id');
        });
    }
};
