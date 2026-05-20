<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loading_cargo_items') || Schema::hasColumn('loading_cargo_items', 'client_key')) {
            return;
        }

        Schema::table('loading_cargo_items', function (Blueprint $table) {
            $table->string('client_key', 80)->nullable()->after('loading_cargo_group_id')->index();
        });

        DB::table('loading_cargo_items')
            ->orderBy('id')
            ->select('id')
            ->lazy()
            ->each(function ($row): void {
                DB::table('loading_cargo_items')
                    ->where('id', $row->id)
                    ->update(['client_key' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('loading_cargo_items') || ! Schema::hasColumn('loading_cargo_items', 'client_key')) {
            return;
        }

        Schema::table('loading_cargo_items', function (Blueprint $table) {
            $table->dropColumn('client_key');
        });
    }
};
