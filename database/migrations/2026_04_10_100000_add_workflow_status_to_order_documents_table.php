<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_documents')) {
            return;
        }

        Schema::table('order_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_documents', 'workflow_status')) {
                $table->string('workflow_status', 40)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_documents')) {
            return;
        }

        Schema::table('order_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('order_documents', 'workflow_status')) {
                $table->dropColumn('workflow_status');
            }
        });
    }
};
