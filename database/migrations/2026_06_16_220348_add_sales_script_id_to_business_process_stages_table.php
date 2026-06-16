<?php

use App\Services\BusinessProcessPlaybookSeederService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_process_stages') || ! Schema::hasTable('sales_scripts')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            if (! Schema::hasColumn('business_process_stages', 'sales_script_id')) {
                $table->foreignId('sales_script_id')
                    ->nullable()
                    ->after('success_criteria')
                    ->constrained('sales_scripts')
                    ->nullOnDelete();
            }
        });

        app(BusinessProcessPlaybookSeederService::class)->seed(true);
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table) {
            if (Schema::hasColumn('business_process_stages', 'sales_script_id')) {
                $table->dropConstrainedForeignId('sales_script_id');
            }
        });
    }
};
