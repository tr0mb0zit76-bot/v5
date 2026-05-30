<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('print_form_templates')) {
            return;
        }

        Schema::table('print_form_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('print_form_templates', 'own_company_id')) {
                $table->foreignId('own_company_id')
                    ->nullable()
                    ->after('contractor_id')
                    ->constrained('contractors')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('print_form_templates', 'transport_scope')) {
                $table->string('transport_scope', 20)
                    ->default('any')
                    ->after('own_company_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('print_form_templates')) {
            return;
        }

        Schema::table('print_form_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('print_form_templates', 'own_company_id')) {
                $table->dropConstrainedForeignId('own_company_id');
            }

            if (Schema::hasColumn('print_form_templates', 'transport_scope')) {
                $table->dropColumn('transport_scope');
            }
        });
    }
};
