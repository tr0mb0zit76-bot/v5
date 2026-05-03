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
        if (! Schema::hasTable('ati_dictionary_items')) {
            Schema::create('ati_dictionary_items', function (Blueprint $table) {
                $table->id();
                $table->string('dictionary', 80);
                $table->unsignedInteger('ati_id')->nullable();
                $table->string('code', 120)->nullable();
                $table->string('label', 255);
                $table->boolean('is_active')->default(true);
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->unique(['dictionary', 'ati_id']);
                $table->unique(['dictionary', 'code']);
                $table->index(['dictionary', 'is_active']);
            });
        }

        if (Schema::hasTable('cargos')) {
            Schema::table('cargos', function (Blueprint $table) {
                if (! Schema::hasColumn('cargos', 'ati_cargo_name')) {
                    $table->string('ati_cargo_name', 500)->nullable()->after('title');
                }

                if (! Schema::hasColumn('cargos', 'weight_value')) {
                    $table->decimal('weight_value', 12, 3)->nullable()->after('weight');
                }

                if (! Schema::hasColumn('cargos', 'weight_unit')) {
                    $table->string('weight_unit', 10)->default('kg')->after('weight_value');
                }

                if (! Schema::hasColumn('cargos', 'diameter')) {
                    $table->decimal('diameter', 10, 2)->nullable()->after('height');
                }

                if (! Schema::hasColumn('cargos', 'cargo_type_label')) {
                    $table->string('cargo_type_label', 255)->nullable()->after('cargo_type_id');
                }

                if (! Schema::hasColumn('cargos', 'pack_type_label')) {
                    $table->string('pack_type_label', 255)->nullable()->after('pack_type_id');
                }

                if (! Schema::hasColumn('cargos', 'is_oversized')) {
                    $table->boolean('is_oversized')->default(false)->after('needs_manipulator');
                }

                if (! Schema::hasColumn('cargos', 'is_fragile')) {
                    $table->boolean('is_fragile')->default(false)->after('is_oversized');
                }

                if (! Schema::hasColumn('cargos', 'ati_cargo_payload')) {
                    $table->json('ati_cargo_payload')->nullable()->after('ati_response');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cargos')) {
            Schema::table('cargos', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('cargos', 'ati_cargo_name') ? 'ati_cargo_name' : null,
                    Schema::hasColumn('cargos', 'weight_value') ? 'weight_value' : null,
                    Schema::hasColumn('cargos', 'weight_unit') ? 'weight_unit' : null,
                    Schema::hasColumn('cargos', 'diameter') ? 'diameter' : null,
                    Schema::hasColumn('cargos', 'cargo_type_label') ? 'cargo_type_label' : null,
                    Schema::hasColumn('cargos', 'pack_type_label') ? 'pack_type_label' : null,
                    Schema::hasColumn('cargos', 'is_oversized') ? 'is_oversized' : null,
                    Schema::hasColumn('cargos', 'is_fragile') ? 'is_fragile' : null,
                    Schema::hasColumn('cargos', 'ati_cargo_payload') ? 'ati_cargo_payload' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('ati_dictionary_items');
    }
};
