<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('one_c_epd_publication_override', 64)
                ->nullable()
                ->after('external_party');
        });

        DB::table('users')
            ->where('email', 'test_2@avtoaliyans.ru')
            ->update(['one_c_epd_publication_override' => 'sandbox']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('one_c_epd_publication_override');
        });
    }
};
