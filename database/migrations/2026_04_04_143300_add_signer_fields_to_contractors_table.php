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
            $table->string('signer_name_nominative')->nullable()->after('contact_person_position');
            $table->string('signer_name_prepositional')->nullable()->after('signer_name_nominative');
            $table->string('signer_authority_basis')->nullable()->after('signer_name_prepositional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn([
                'signer_name_nominative',
                'signer_name_prepositional',
                'signer_authority_basis',
            ]);
        });
    }
};
