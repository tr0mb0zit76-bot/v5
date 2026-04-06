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

        Schema::table('contractors', function (Blueprint $table) {
            if (! Schema::hasColumn('contractors', 'signer_name_nominative')) {
                $table->string('signer_name_nominative')->nullable();
            }

            if (! Schema::hasColumn('contractors', 'signer_name_prepositional')) {
                $table->string('signer_name_prepositional')->nullable();
            }

            if (! Schema::hasColumn('contractors', 'signer_authority_basis')) {
                $table->string('signer_authority_basis')->nullable();
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

        Schema::table('contractors', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('contractors', 'signer_name_nominative') ? 'signer_name_nominative' : null,
                Schema::hasColumn('contractors', 'signer_name_prepositional') ? 'signer_name_prepositional' : null,
                Schema::hasColumn('contractors', 'signer_authority_basis') ? 'signer_authority_basis' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
