<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'channel')) {
                $table->string('channel', 24)->default('internal')->after('type');
            }

            if (! Schema::hasColumn('conversations', 'contractor_id')) {
                $table->foreignId('contractor_id')->nullable()->after('channel')->constrained('contractors')->nullOnDelete();
            }

            if (! Schema::hasColumn('conversations', 'external_party')) {
                $table->string('external_party', 16)->nullable()->after('contractor_id');
            }

            if (! Schema::hasColumn('conversations', 'primary_staff_user_id')) {
                $table->foreignId('primary_staff_user_id')->nullable()->after('external_party')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('conversations', function (Blueprint $table): void {
            if (
                Schema::hasColumn('conversations', 'channel')
                && Schema::hasColumn('conversations', 'contractor_id')
                && Schema::hasColumn('conversations', 'external_party')
                && Schema::hasColumn('conversations', 'primary_staff_user_id')
            ) {
                $table->index(
                    ['channel', 'contractor_id', 'external_party', 'primary_staff_user_id'],
                    'conversations_counterparty_thread_idx',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_counterparty_thread_idx');

            if (Schema::hasColumn('conversations', 'primary_staff_user_id')) {
                $table->dropConstrainedForeignId('primary_staff_user_id');
            }

            if (Schema::hasColumn('conversations', 'external_party')) {
                $table->dropColumn('external_party');
            }

            if (Schema::hasColumn('conversations', 'contractor_id')) {
                $table->dropConstrainedForeignId('contractor_id');
            }

            if (Schema::hasColumn('conversations', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
