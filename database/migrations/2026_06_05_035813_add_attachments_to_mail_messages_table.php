<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_messages') || Schema::hasColumn('mail_messages', 'attachments')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_messages') || ! Schema::hasColumn('mail_messages', 'attachments')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
