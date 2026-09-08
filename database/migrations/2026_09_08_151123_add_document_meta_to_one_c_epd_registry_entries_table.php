<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('one_c_epd_registry_entries', function (Blueprint $table) {
            $table->json('document_meta')->nullable()->after('raw_payload');
        });
    }

    public function down(): void
    {
        Schema::table('one_c_epd_registry_entries', function (Blueprint $table) {
            $table->dropColumn('document_meta');
        });
    }
};
