<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_messages', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('body')->constrained('orders')->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_messages', 'message_type')) {
                $table->string('message_type', 24)->default('text')->after('order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_messages', 'order_id')) {
                $table->dropConstrainedForeignId('order_id');
            }

            if (Schema::hasColumn('chat_messages', 'message_type')) {
                $table->dropColumn('message_type');
            }
        });
    }
};
