<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cargos')) {
            return;
        }

        Schema::table('cargos', function (Blueprint $table) {
            if (! Schema::hasColumn('cargos', 'dimension_unit')) {
                $table->string('dimension_unit', 10)->default('m')->after('height');
            }

            if (! Schema::hasColumn('cargos', 'length_value')) {
                $table->decimal('length_value', 12, 3)->nullable()->after('dimension_unit');
            }

            if (! Schema::hasColumn('cargos', 'width_value')) {
                $table->decimal('width_value', 12, 3)->nullable()->after('length_value');
            }

            if (! Schema::hasColumn('cargos', 'height_value')) {
                $table->decimal('height_value', 12, 3)->nullable()->after('width_value');
            }
        });

        if (Schema::hasColumn('cargos', 'length_value')) {
            DB::table('cargos')
                ->whereNull('length_value')
                ->whereNotNull('length')
                ->update([
                    'length_value' => DB::raw('length'),
                    'dimension_unit' => 'm',
                ]);

            DB::table('cargos')
                ->whereNull('width_value')
                ->whereNotNull('width')
                ->update([
                    'width_value' => DB::raw('width'),
                ]);

            DB::table('cargos')
                ->whereNull('height_value')
                ->whereNotNull('height')
                ->update([
                    'height_value' => DB::raw('height'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cargos')) {
            return;
        }

        Schema::table('cargos', function (Blueprint $table) {
            foreach (['height_value', 'width_value', 'length_value', 'dimension_unit'] as $column) {
                if (Schema::hasColumn('cargos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
