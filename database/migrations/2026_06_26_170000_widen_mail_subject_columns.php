<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_threads') && Schema::hasColumn('mail_threads', 'subject')) {
            DB::statement('ALTER TABLE `mail_threads` MODIFY `subject` TEXT NOT NULL');
        }

        if (Schema::hasTable('mail_messages') && Schema::hasColumn('mail_messages', 'subject')) {
            DB::statement('ALTER TABLE `mail_messages` MODIFY `subject` TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_threads') && Schema::hasColumn('mail_threads', 'subject')) {
            DB::statement('ALTER TABLE `mail_threads` MODIFY `subject` VARCHAR(255) NOT NULL');
        }

        if (Schema::hasTable('mail_messages') && Schema::hasColumn('mail_messages', 'subject')) {
            DB::statement('ALTER TABLE `mail_messages` MODIFY `subject` VARCHAR(255) NOT NULL');
        }
    }
};
