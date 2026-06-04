<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'mail_imap_secret')) {
                $table->text('mail_imap_secret')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'mail_sync_enabled')) {
                $table->boolean('mail_sync_enabled')->default(true)->after('mail_imap_secret');
            }

            if (! Schema::hasColumn('users', 'mail_last_sync_at')) {
                $table->timestamp('mail_last_sync_at')->nullable()->after('mail_sync_enabled');
            }

            if (! Schema::hasColumn('users', 'mail_last_sync_error')) {
                $table->string('mail_last_sync_error', 500)->nullable()->after('mail_last_sync_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'mail_imap_secret',
                'mail_sync_enabled',
                'mail_last_sync_at',
                'mail_last_sync_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
