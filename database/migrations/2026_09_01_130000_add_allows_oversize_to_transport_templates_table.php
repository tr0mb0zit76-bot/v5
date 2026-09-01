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
        if (! Schema::hasTable('transport_templates')) {
            return;
        }

        Schema::table('transport_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('transport_templates', 'allows_oversize')) {
                $table->boolean('allows_oversize')->default(false)->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('transport_templates')) {
            return;
        }

        Schema::table('transport_templates', function (Blueprint $table) {
            if (Schema::hasColumn('transport_templates', 'allows_oversize')) {
                $table->dropColumn('allows_oversize');
            }
        });
    }
};
