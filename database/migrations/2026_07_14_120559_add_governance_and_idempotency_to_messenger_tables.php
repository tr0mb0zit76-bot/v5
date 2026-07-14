<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('posting_policy', 20)
                ->default('members')
                ->after('created_by');
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->string('role', 20)
                ->default('member')
                ->after('user_id');
        });

        DB::table('conversations')
            ->where('type', 'group')
            ->whereNotNull('created_by')
            ->select(['id', 'created_by'])
            ->orderBy('id')
            ->each(function (object $conversation): void {
                DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $conversation->created_by)
                    ->update(['role' => 'owner']);
            });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->uuid('client_message_id')
                ->nullable()
                ->after('user_id');
            $table->unique(
                ['conversation_id', 'user_id', 'client_message_id'],
                'chat_messages_client_id_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropUnique('chat_messages_client_id_unique');
            $table->dropColumn('client_message_id');
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn('role');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('posting_policy');
        });
    }
};
