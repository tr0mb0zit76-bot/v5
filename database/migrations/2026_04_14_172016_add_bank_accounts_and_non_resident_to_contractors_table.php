<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            if (! Schema::hasColumn('contractors', 'bank_accounts')) {
                $table->json('bank_accounts')->nullable()->after('correspondent_account');
            }

            if (! Schema::hasColumn('contractors', 'is_non_resident')) {
                $table->boolean('is_non_resident')->default(false)->after('is_own_company');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            if (Schema::hasColumn('contractors', 'bank_accounts')) {
                $table->dropColumn('bank_accounts');
            }

            if (Schema::hasColumn('contractors', 'is_non_resident')) {
                $table->dropColumn('is_non_resident');
            }
        });
    }
};
