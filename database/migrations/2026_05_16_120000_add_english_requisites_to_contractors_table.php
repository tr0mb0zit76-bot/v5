<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            $table->boolean('has_english_requisites')->default(false)->after('is_non_resident');
            $table->string('name_en')->nullable()->after('full_name');
            $table->string('full_name_en')->nullable()->after('name_en');
            $table->string('legal_address_en')->nullable()->after('postal_address');
            $table->string('actual_address_en')->nullable()->after('legal_address_en');
            $table->string('postal_address_en')->nullable()->after('actual_address_en');
            $table->string('contact_person_en')->nullable()->after('contact_person_position');
            $table->string('bank_name_en')->nullable()->after('bank_name');
            $table->string('signer_name_nominative_en')->nullable()->after('signer_authority_basis');
            $table->string('signer_name_prepositional_en')->nullable()->after('signer_name_nominative_en');
            $table->string('signer_position_en')->nullable()->after('signer_name_prepositional_en');
            $table->string('signer_authority_basis_en')->nullable()->after('signer_position_en');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            $table->dropColumn([
                'has_english_requisites',
                'name_en',
                'full_name_en',
                'legal_address_en',
                'actual_address_en',
                'postal_address_en',
                'contact_person_en',
                'bank_name_en',
                'signer_name_nominative_en',
                'signer_name_prepositional_en',
                'signer_position_en',
                'signer_authority_basis_en',
            ]);
        });
    }
};
