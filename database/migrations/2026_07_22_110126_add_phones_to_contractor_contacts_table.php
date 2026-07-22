<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_contacts')) {
            return;
        }

        Schema::table('contractor_contacts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractor_contacts', 'phones')) {
                $table->json('phones')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractor_contacts')) {
            return;
        }

        Schema::table('contractor_contacts', function (Blueprint $table): void {
            if (Schema::hasColumn('contractor_contacts', 'phones')) {
                $table->dropColumn('phones');
            }
        });
    }
};
