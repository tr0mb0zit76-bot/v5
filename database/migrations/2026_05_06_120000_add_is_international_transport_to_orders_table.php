<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'is_international_transport')) {
            return;
        }

        // `svh_name` добавляется более поздней миграцией — на частично накатанной базе колонки может не быть.
        $after = match (true) {
            Schema::hasColumn('orders', 'svh_name') => 'svh_name',
            Schema::hasColumn('orders', 'special_notes') => 'special_notes',
            default => null,
        };

        Schema::table('orders', function (Blueprint $table) use ($after): void {
            if ($after !== null) {
                $table->boolean('is_international_transport')->default(false)->after($after);

                return;
            }

            $table->boolean('is_international_transport')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'is_international_transport')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('is_international_transport');
            });
        }
    }
};
