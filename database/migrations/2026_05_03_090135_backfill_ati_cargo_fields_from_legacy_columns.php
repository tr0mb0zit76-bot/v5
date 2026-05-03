<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cargos')) {
            return;
        }

        $columns = [
            'title',
            'ati_cargo_name',
            'weight',
            'weight_value',
            'weight_unit',
            'volume',
            'cargo_type',
            'cargo_type_label',
            'packing_type',
            'pack_type_label',
            'is_oversized',
            'is_fragile',
        ];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('cargos', $column)) {
                return;
            }
        }

        DB::table('cargos')
            ->whereNull('weight_value')
            ->whereNotNull('weight')
            ->update([
                'weight_value' => DB::raw('weight'),
                'weight_unit' => 'kg',
            ]);

        DB::table('cargos')
            ->whereNull('ati_cargo_name')
            ->whereNotNull('title')
            ->update([
                'ati_cargo_name' => DB::raw('title'),
            ]);

        DB::table('cargos')
            ->whereNull('cargo_type_label')
            ->whereNotNull('cargo_type')
            ->update([
                'cargo_type_label' => DB::raw('cargo_type'),
            ]);

        DB::table('cargos')
            ->whereNull('pack_type_label')
            ->whereNotNull('packing_type')
            ->update([
                'pack_type_label' => DB::raw('packing_type'),
            ]);

        DB::table('cargos')
            ->where('cargo_type', 'oversized')
            ->update([
                'is_oversized' => true,
            ]);

        DB::table('cargos')
            ->where('cargo_type', 'fragile')
            ->update([
                'is_fragile' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfilled values are intentionally preserved. The schema migration removes
        // these columns if it is rolled back.
    }
};
