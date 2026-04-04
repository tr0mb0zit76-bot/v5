<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'has_signing_authority')) {
                    $table->boolean('has_signing_authority')->default(false)->after('columns_config');
                }
            });

            DB::table('roles')
                ->where('name', 'supervisor')
                ->update(['has_signing_authority' => true]);
        }

        if (Schema::hasTable('print_form_templates')) {
            Schema::table('print_form_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('print_form_templates', 'entity_type')) {
                    $table->string('entity_type', 50)->default('order')->after('name')->index();
                }

                if (! Schema::hasColumn('print_form_templates', 'source_type')) {
                    $table->string('source_type', 50)->default('system')->after('party')->index();
                }

                if (! Schema::hasColumn('print_form_templates', 'contractor_id')) {
                    $table->foreignId('contractor_id')->nullable()->after('source_type')->constrained('contractors')->nullOnDelete();
                }

                if (! Schema::hasColumn('print_form_templates', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('contractor_id')->index();
                }

                if (! Schema::hasColumn('print_form_templates', 'file_disk')) {
                    $table->string('file_disk', 50)->nullable()->after('is_default');
                }

                if (! Schema::hasColumn('print_form_templates', 'file_path')) {
                    $table->string('file_path')->nullable()->after('file_disk');
                }

                if (! Schema::hasColumn('print_form_templates', 'original_filename')) {
                    $table->string('original_filename')->nullable()->after('file_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('print_form_templates')) {
            Schema::table('print_form_templates', function (Blueprint $table) {
                if (Schema::hasColumn('print_form_templates', 'contractor_id')) {
                    try {
                        $table->dropConstrainedForeignId('contractor_id');
                    } catch (Throwable) {
                    }
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('print_form_templates', 'entity_type') ? 'entity_type' : null,
                    Schema::hasColumn('print_form_templates', 'source_type') ? 'source_type' : null,
                    Schema::hasColumn('print_form_templates', 'is_default') ? 'is_default' : null,
                    Schema::hasColumn('print_form_templates', 'file_disk') ? 'file_disk' : null,
                    Schema::hasColumn('print_form_templates', 'file_path') ? 'file_path' : null,
                    Schema::hasColumn('print_form_templates', 'original_filename') ? 'original_filename' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'has_signing_authority')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('has_signing_authority');
            });
        }
    }
};
