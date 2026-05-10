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
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'non_resident_corr_bank_name')) {
                $table->string('non_resident_corr_bank_name')->nullable();
            }

            if (! Schema::hasColumn('contractors', 'non_resident_corr_bank_swift')) {
                $table->string('non_resident_corr_bank_swift', 11)->nullable();
            }

            if (! Schema::hasColumn('contractors', 'non_resident_corr_bank_account')) {
                $table->string('non_resident_corr_bank_account', 64)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (Schema::hasColumn('contractors', 'non_resident_corr_bank_account')) {
                $table->dropColumn('non_resident_corr_bank_account');
            }

            if (Schema::hasColumn('contractors', 'non_resident_corr_bank_swift')) {
                $table->dropColumn('non_resident_corr_bank_swift');
            }

            if (Schema::hasColumn('contractors', 'non_resident_corr_bank_name')) {
                $table->dropColumn('non_resident_corr_bank_name');
            }
        });
    }
};
