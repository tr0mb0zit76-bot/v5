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
        if (! Schema::hasTable('load_board_posts')) {
            return;
        }

        Schema::table('load_board_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('load_board_posts', 'ati_cargo_name')) {
                $table->string('ati_cargo_name', 500)->nullable()->after('cargo_name');
            }

            if (! Schema::hasColumn('load_board_posts', 'cargo_type_id')) {
                $table->unsignedInteger('cargo_type_id')->nullable()->after('cargo_volume');
            }

            if (! Schema::hasColumn('load_board_posts', 'cargo_type')) {
                $table->string('cargo_type', 120)->nullable()->after('cargo_type_id');
            }

            if (! Schema::hasColumn('load_board_posts', 'cargo_type_label')) {
                $table->string('cargo_type_label')->nullable()->after('cargo_type');
            }

            if (! Schema::hasColumn('load_board_posts', 'pack_type_id')) {
                $table->unsignedInteger('pack_type_id')->nullable()->after('cargo_type_label');
            }

            if (! Schema::hasColumn('load_board_posts', 'package_type')) {
                $table->string('package_type', 120)->nullable()->after('pack_type_id');
            }

            if (! Schema::hasColumn('load_board_posts', 'pack_type_label')) {
                $table->string('pack_type_label')->nullable()->after('package_type');
            }

            if (! Schema::hasColumn('load_board_posts', 'package_count')) {
                $table->unsignedInteger('package_count')->nullable()->after('pack_type_label');
            }

            if (! Schema::hasColumn('load_board_posts', 'loading_type_id')) {
                $table->unsignedInteger('loading_type_id')->nullable()->after('package_count');
            }

            if (! Schema::hasColumn('load_board_posts', 'loading_type_code')) {
                $table->string('loading_type_code', 120)->nullable()->after('loading_type_id');
            }

            if (! Schema::hasColumn('load_board_posts', 'loading_type_label')) {
                $table->string('loading_type_label')->nullable()->after('loading_type_code');
            }

            if (! Schema::hasColumn('load_board_posts', 'loading_type_items')) {
                $table->json('loading_type_items')->nullable()->after('loading_type_label');
            }

            if (! Schema::hasColumn('load_board_posts', 'truck_body_type_id')) {
                $table->unsignedInteger('truck_body_type_id')->nullable()->after('loading_type_items');
            }

            if (! Schema::hasColumn('load_board_posts', 'truck_body_type_code')) {
                $table->string('truck_body_type_code', 120)->nullable()->after('truck_body_type_id');
            }

            if (! Schema::hasColumn('load_board_posts', 'truck_body_type_label')) {
                $table->string('truck_body_type_label')->nullable()->after('truck_body_type_code');
            }

            if (! Schema::hasColumn('load_board_posts', 'truck_body_type_items')) {
                $table->json('truck_body_type_items')->nullable()->after('truck_body_type_label');
            }

            if (! Schema::hasColumn('load_board_posts', 'trailer_type_id')) {
                $table->unsignedInteger('trailer_type_id')->nullable()->after('truck_body_type_items');
            }

            if (! Schema::hasColumn('load_board_posts', 'trailer_type_code')) {
                $table->string('trailer_type_code', 120)->nullable()->after('trailer_type_id');
            }

            if (! Schema::hasColumn('load_board_posts', 'trailer_type_label')) {
                $table->string('trailer_type_label')->nullable()->after('trailer_type_code');
            }

            if (! Schema::hasColumn('load_board_posts', 'trailer_type_items')) {
                $table->json('trailer_type_items')->nullable()->after('trailer_type_label');
            }

            if (! Schema::hasColumn('load_board_posts', 'length')) {
                $table->decimal('length', 10, 2)->nullable()->after('trailer_type_items');
            }

            if (! Schema::hasColumn('load_board_posts', 'width')) {
                $table->decimal('width', 10, 2)->nullable()->after('length');
            }

            if (! Schema::hasColumn('load_board_posts', 'height')) {
                $table->decimal('height', 10, 2)->nullable()->after('width');
            }

            if (! Schema::hasColumn('load_board_posts', 'diameter')) {
                $table->decimal('diameter', 10, 2)->nullable()->after('height');
            }

            if (! Schema::hasColumn('load_board_posts', 'is_hazardous')) {
                $table->boolean('is_hazardous')->default(false)->after('diameter');
            }

            if (! Schema::hasColumn('load_board_posts', 'hazard_class')) {
                $table->string('hazard_class', 50)->nullable()->after('is_hazardous');
            }

            if (! Schema::hasColumn('load_board_posts', 'needs_temperature')) {
                $table->boolean('needs_temperature')->default(false)->after('hazard_class');
            }

            if (! Schema::hasColumn('load_board_posts', 'temp_min')) {
                $table->decimal('temp_min', 6, 2)->nullable()->after('needs_temperature');
            }

            if (! Schema::hasColumn('load_board_posts', 'temp_max')) {
                $table->decimal('temp_max', 6, 2)->nullable()->after('temp_min');
            }

            if (! Schema::hasColumn('load_board_posts', 'is_oversized')) {
                $table->boolean('is_oversized')->default(false)->after('temp_max');
            }

            if (! Schema::hasColumn('load_board_posts', 'is_fragile')) {
                $table->boolean('is_fragile')->default(false)->after('is_oversized');
            }

            if (! Schema::hasColumn('load_board_posts', 'hs_code')) {
                $table->string('hs_code', 32)->nullable()->after('is_fragile');
            }

            if (! Schema::hasColumn('load_board_posts', 'ati_cargo_payload')) {
                $table->json('ati_cargo_payload')->nullable()->after('hs_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('load_board_posts')) {
            return;
        }

        Schema::table('load_board_posts', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('load_board_posts', 'ati_cargo_name') ? 'ati_cargo_name' : null,
                Schema::hasColumn('load_board_posts', 'cargo_type_id') ? 'cargo_type_id' : null,
                Schema::hasColumn('load_board_posts', 'cargo_type') ? 'cargo_type' : null,
                Schema::hasColumn('load_board_posts', 'cargo_type_label') ? 'cargo_type_label' : null,
                Schema::hasColumn('load_board_posts', 'pack_type_id') ? 'pack_type_id' : null,
                Schema::hasColumn('load_board_posts', 'package_type') ? 'package_type' : null,
                Schema::hasColumn('load_board_posts', 'pack_type_label') ? 'pack_type_label' : null,
                Schema::hasColumn('load_board_posts', 'package_count') ? 'package_count' : null,
                Schema::hasColumn('load_board_posts', 'loading_type_id') ? 'loading_type_id' : null,
                Schema::hasColumn('load_board_posts', 'loading_type_code') ? 'loading_type_code' : null,
                Schema::hasColumn('load_board_posts', 'loading_type_label') ? 'loading_type_label' : null,
                Schema::hasColumn('load_board_posts', 'loading_type_items') ? 'loading_type_items' : null,
                Schema::hasColumn('load_board_posts', 'truck_body_type_id') ? 'truck_body_type_id' : null,
                Schema::hasColumn('load_board_posts', 'truck_body_type_code') ? 'truck_body_type_code' : null,
                Schema::hasColumn('load_board_posts', 'truck_body_type_label') ? 'truck_body_type_label' : null,
                Schema::hasColumn('load_board_posts', 'truck_body_type_items') ? 'truck_body_type_items' : null,
                Schema::hasColumn('load_board_posts', 'trailer_type_id') ? 'trailer_type_id' : null,
                Schema::hasColumn('load_board_posts', 'trailer_type_code') ? 'trailer_type_code' : null,
                Schema::hasColumn('load_board_posts', 'trailer_type_label') ? 'trailer_type_label' : null,
                Schema::hasColumn('load_board_posts', 'trailer_type_items') ? 'trailer_type_items' : null,
                Schema::hasColumn('load_board_posts', 'length') ? 'length' : null,
                Schema::hasColumn('load_board_posts', 'width') ? 'width' : null,
                Schema::hasColumn('load_board_posts', 'height') ? 'height' : null,
                Schema::hasColumn('load_board_posts', 'diameter') ? 'diameter' : null,
                Schema::hasColumn('load_board_posts', 'is_hazardous') ? 'is_hazardous' : null,
                Schema::hasColumn('load_board_posts', 'hazard_class') ? 'hazard_class' : null,
                Schema::hasColumn('load_board_posts', 'needs_temperature') ? 'needs_temperature' : null,
                Schema::hasColumn('load_board_posts', 'temp_min') ? 'temp_min' : null,
                Schema::hasColumn('load_board_posts', 'temp_max') ? 'temp_max' : null,
                Schema::hasColumn('load_board_posts', 'is_oversized') ? 'is_oversized' : null,
                Schema::hasColumn('load_board_posts', 'is_fragile') ? 'is_fragile' : null,
                Schema::hasColumn('load_board_posts', 'hs_code') ? 'hs_code' : null,
                Schema::hasColumn('load_board_posts', 'ati_cargo_payload') ? 'ati_cargo_payload' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
