<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('improvement_adoptions')) {
            return;
        }

        if (! Schema::hasColumn('improvement_adoptions', 'meta')) {
            Schema::table('improvement_adoptions', function (Blueprint $table): void {
                $table->json('meta')->nullable()->after('summary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('improvement_adoptions') && Schema::hasColumn('improvement_adoptions', 'meta')) {
            Schema::table('improvement_adoptions', function (Blueprint $table): void {
                $table->dropColumn('meta');
            });
        }
    }
};
