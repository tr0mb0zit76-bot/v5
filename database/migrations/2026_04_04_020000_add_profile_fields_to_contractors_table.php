<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'short_description')) {
                $table->text('short_description')->nullable()->after('full_name');
            }

            if (! Schema::hasColumn('contractors', 'activity_types')) {
                $table->json('activity_types')->nullable()->after('specializations');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('contractors', 'short_description')) {
                $columnsToDrop[] = 'short_description';
            }

            if (Schema::hasColumn('contractors', 'activity_types')) {
                $columnsToDrop[] = 'activity_types';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
