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
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'company_initiative_id')) {
                $table->unsignedBigInteger('company_initiative_id')->nullable()->after('contractor_id')->index();
            }

            if (! Schema::hasColumn('tasks', 'company_initiative_milestone_id')) {
                $table->unsignedBigInteger('company_initiative_milestone_id')->nullable()->after('company_initiative_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'company_initiative_milestone_id')) {
                $table->dropColumn('company_initiative_milestone_id');
            }

            if (Schema::hasColumn('tasks', 'company_initiative_id')) {
                $table->dropColumn('company_initiative_id');
            }
        });
    }
};
