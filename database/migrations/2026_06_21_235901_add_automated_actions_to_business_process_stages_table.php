<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_process_stages', 'automated_actions')) {
                $table->json('automated_actions')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_process_stages')) {
            return;
        }

        Schema::table('business_process_stages', function (Blueprint $table): void {
            if (Schema::hasColumn('business_process_stages', 'automated_actions')) {
                $table->dropColumn('automated_actions');
            }
        });
    }
};
