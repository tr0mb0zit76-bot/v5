<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contractors') && Schema::hasColumn('contractors', 'type')) {
            DB::statement("ALTER TABLE contractors MODIFY COLUMN type ENUM('customer','carrier','contractor','both') NOT NULL DEFAULT 'both'");
        }

        if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payment_schedules', 'party')) {
            DB::statement("ALTER TABLE payment_schedules MODIFY COLUMN party ENUM('customer','carrier','contractor') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payment_schedules', 'party')) {
            DB::statement("ALTER TABLE payment_schedules MODIFY COLUMN party ENUM('customer','carrier') NOT NULL");
        }

        if (Schema::hasTable('contractors') && Schema::hasColumn('contractors', 'type')) {
            DB::statement("ALTER TABLE contractors MODIFY COLUMN type ENUM('customer','carrier','both') NOT NULL DEFAULT 'both'");
        }
    }
};
