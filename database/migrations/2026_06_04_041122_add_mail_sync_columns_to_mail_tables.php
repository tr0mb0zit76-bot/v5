<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_messages')) {
            Schema::table('mail_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('mail_messages', 'internet_message_id')) {
                    $table->string('internet_message_id', 500)->nullable()->after('direction');
                    $table->unique('internet_message_id', 'mail_messages_internet_message_id_unique');
                }

                if (! Schema::hasColumn('mail_messages', 'mailbox_user_id')) {
                    $table->unsignedBigInteger('mailbox_user_id')->nullable()->after('created_by');
                    $table->index('mailbox_user_id', 'mail_messages_mailbox_user_id_idx');
                }
            });
        }

        if (Schema::hasTable('mail_threads')) {
            Schema::table('mail_threads', function (Blueprint $table): void {
                if (! Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
                    $table->unsignedBigInteger('mailbox_user_id')->nullable()->after('created_by');
                    $table->index('mailbox_user_id', 'mail_threads_mailbox_user_id_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_messages')) {
            Schema::table('mail_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('mail_messages', 'internet_message_id')) {
                    $table->dropUnique('mail_messages_internet_message_id_unique');
                    $table->dropColumn('internet_message_id');
                }

                if (Schema::hasColumn('mail_messages', 'mailbox_user_id')) {
                    $table->dropIndex('mail_messages_mailbox_user_id_idx');
                    $table->dropColumn('mailbox_user_id');
                }
            });
        }

        if (Schema::hasTable('mail_threads')) {
            Schema::table('mail_threads', function (Blueprint $table): void {
                if (Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
                    $table->dropIndex('mail_threads_mailbox_user_id_idx');
                    $table->dropColumn('mailbox_user_id');
                }
            });
        }
    }
};
