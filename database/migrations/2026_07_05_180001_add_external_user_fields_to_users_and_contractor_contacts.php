<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'is_external')) {
                    $table->boolean('is_external')->default(false)->after('is_active');
                }

                if (! Schema::hasColumn('users', 'contractor_id')) {
                    $table->foreignId('contractor_id')->nullable()->after('is_external')->constrained('contractors')->nullOnDelete();
                }

                if (! Schema::hasColumn('users', 'contractor_contact_id')) {
                    $table->foreignId('contractor_contact_id')->nullable()->after('contractor_id')->constrained('contractor_contacts')->nullOnDelete();
                }

                if (! Schema::hasColumn('users', 'external_party')) {
                    $table->string('external_party', 16)->nullable()->after('contractor_contact_id');
                }
            });

            if (Schema::hasColumn('users', 'contractor_contact_id')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('contractor_contact_id', 'users_contractor_contact_id_unique');
                });
            }
        }

        if (Schema::hasTable('contractor_contacts') && ! Schema::hasColumn('contractor_contacts', 'is_traklo_primary')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                $table->boolean('is_traklo_primary')->default(false)->after('is_primary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'contractor_contact_id')) {
                    $table->dropUnique('users_contractor_contact_id_unique');
                    $table->dropConstrainedForeignId('contractor_contact_id');
                }

                if (Schema::hasColumn('users', 'contractor_id')) {
                    $table->dropConstrainedForeignId('contractor_id');
                }

                if (Schema::hasColumn('users', 'external_party')) {
                    $table->dropColumn('external_party');
                }

                if (Schema::hasColumn('users', 'is_external')) {
                    $table->dropColumn('is_external');
                }
            });
        }

        if (Schema::hasTable('contractor_contacts') && Schema::hasColumn('contractor_contacts', 'is_traklo_primary')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                $table->dropColumn('is_traklo_primary');
            });
        }
    }
};
