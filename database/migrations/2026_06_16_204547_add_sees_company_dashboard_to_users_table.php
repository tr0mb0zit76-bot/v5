<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'sees_company_dashboard')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('sees_company_dashboard')->default(false)->after('can_management_accounting');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'sees_company_dashboard')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('sees_company_dashboard');
        });
    }
};
