<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lead_offers')) {
            return;
        }

        Schema::table('lead_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_offers', 'title')) {
                $table->string('title')->nullable()->after('number');
            }

            if (! Schema::hasColumn('lead_offers', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('generated_file_path');
            }

            if (! Schema::hasColumn('lead_offers', 'last_mail_thread_id')) {
                $table->unsignedBigInteger('last_mail_thread_id')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_offers')) {
            return;
        }

        Schema::table('lead_offers', function (Blueprint $table) {
            foreach (['title', 'sent_at', 'last_mail_thread_id'] as $column) {
                if (Schema::hasColumn('lead_offers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
