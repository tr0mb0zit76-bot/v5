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
        if (! Schema::hasTable('cargos')) {
            return;
        }

        Schema::table('cargos', function (Blueprint $table) {
            if (! Schema::hasColumn('cargos', 'loading_type_items')) {
                $table->json('loading_type_items')->nullable()->after('loading_type_label');
            }

            if (! Schema::hasColumn('cargos', 'truck_body_type_items')) {
                $table->json('truck_body_type_items')->nullable()->after('truck_body_type_label');
            }

            if (! Schema::hasColumn('cargos', 'trailer_type_items')) {
                $table->json('trailer_type_items')->nullable()->after('trailer_type_label');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('cargos')) {
            return;
        }

        Schema::table('cargos', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('cargos', 'loading_type_items') ? 'loading_type_items' : null,
                Schema::hasColumn('cargos', 'truck_body_type_items') ? 'truck_body_type_items' : null,
                Schema::hasColumn('cargos', 'trailer_type_items') ? 'trailer_type_items' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
