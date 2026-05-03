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
            if (! Schema::hasColumn('cargos', 'loading_type_id')) {
                $table->unsignedInteger('loading_type_id')->nullable()->after('pack_type_label');
            }

            if (! Schema::hasColumn('cargos', 'loading_type_code')) {
                $table->string('loading_type_code', 120)->nullable()->after('loading_type_id');
            }

            if (! Schema::hasColumn('cargos', 'loading_type_label')) {
                $table->string('loading_type_label', 255)->nullable()->after('loading_type_code');
            }

            if (! Schema::hasColumn('cargos', 'truck_body_type_id')) {
                $table->unsignedInteger('truck_body_type_id')->nullable()->after('loading_type_label');
            }

            if (! Schema::hasColumn('cargos', 'truck_body_type_code')) {
                $table->string('truck_body_type_code', 120)->nullable()->after('truck_body_type_id');
            }

            if (! Schema::hasColumn('cargos', 'truck_body_type_label')) {
                $table->string('truck_body_type_label', 255)->nullable()->after('truck_body_type_code');
            }

            if (! Schema::hasColumn('cargos', 'trailer_type_id')) {
                $table->unsignedInteger('trailer_type_id')->nullable()->after('truck_body_type_label');
            }

            if (! Schema::hasColumn('cargos', 'trailer_type_code')) {
                $table->string('trailer_type_code', 120)->nullable()->after('trailer_type_id');
            }

            if (! Schema::hasColumn('cargos', 'trailer_type_label')) {
                $table->string('trailer_type_label', 255)->nullable()->after('trailer_type_code');
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
                Schema::hasColumn('cargos', 'loading_type_id') ? 'loading_type_id' : null,
                Schema::hasColumn('cargos', 'loading_type_code') ? 'loading_type_code' : null,
                Schema::hasColumn('cargos', 'loading_type_label') ? 'loading_type_label' : null,
                Schema::hasColumn('cargos', 'truck_body_type_id') ? 'truck_body_type_id' : null,
                Schema::hasColumn('cargos', 'truck_body_type_code') ? 'truck_body_type_code' : null,
                Schema::hasColumn('cargos', 'truck_body_type_label') ? 'truck_body_type_label' : null,
                Schema::hasColumn('cargos', 'trailer_type_id') ? 'trailer_type_id' : null,
                Schema::hasColumn('cargos', 'trailer_type_code') ? 'trailer_type_code' : null,
                Schema::hasColumn('cargos', 'trailer_type_label') ? 'trailer_type_label' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
