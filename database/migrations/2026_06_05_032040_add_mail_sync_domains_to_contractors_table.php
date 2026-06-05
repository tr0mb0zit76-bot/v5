<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors') || Schema::hasColumn('contractors', 'mail_sync_domains')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->json('mail_sync_domains')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'mail_sync_domains')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('mail_sync_domains');
        });
    }
};
