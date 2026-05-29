<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('edo_provider', 32)->nullable()->after('signer_authority_basis');
            $table->string('edo_number', 255)->nullable()->after('edo_provider');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['edo_provider', 'edo_number']);
        });
    }
};
