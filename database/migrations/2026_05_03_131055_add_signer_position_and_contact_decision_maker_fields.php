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
        if (Schema::hasTable('contractors') && ! Schema::hasColumn('contractors', 'signer_position')) {
            Schema::table('contractors', function (Blueprint $table): void {
                $column = $table->string('signer_position')->nullable();

                if (Schema::hasColumn('contractors', 'signer_name_prepositional')) {
                    $column->after('signer_name_prepositional');
                }
            });
        }

        if (Schema::hasTable('contractor_contacts') && ! Schema::hasColumn('contractor_contacts', 'is_decision_maker')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                $column = $table->boolean('is_decision_maker')->default(false);

                if (Schema::hasColumn('contractor_contacts', 'is_primary')) {
                    $column->after('is_primary');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contractor_contacts') && Schema::hasColumn('contractor_contacts', 'is_decision_maker')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                $table->dropColumn('is_decision_maker');
            });
        }

        if (Schema::hasTable('contractors') && Schema::hasColumn('contractors', 'signer_position')) {
            Schema::table('contractors', function (Blueprint $table): void {
                $table->dropColumn('signer_position');
            });
        }
    }
};
