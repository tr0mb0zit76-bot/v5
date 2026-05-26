<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'work_status')) {
                $table->string('work_status', 32)->default('active')->after('is_active');
            }

            if (! Schema::hasColumn('contractors', 'work_pause_is_automatic')) {
                $table->boolean('work_pause_is_automatic')->default(false)->after('work_status');
            }

            if (! Schema::hasColumn('contractors', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_verified');
            }
        });

        if (Schema::hasColumn('contractors', 'is_verified') && Schema::hasColumn('contractors', 'verified_at')) {
            DB::table('contractors')
                ->where('is_verified', true)
                ->whereNull('verified_at')
                ->update(['verified_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (Schema::hasColumn('contractors', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('contractors', 'work_pause_is_automatic')) {
                $table->dropColumn('work_pause_is_automatic');
            }

            if (Schema::hasColumn('contractors', 'work_status')) {
                $table->dropColumn('work_status');
            }
        });
    }
};
