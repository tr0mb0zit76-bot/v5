<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_script_transitions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_script_transitions', 'customer_label')) {
                $table->string('customer_label', 500)->nullable()->after('sales_script_reaction_class_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_script_transitions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_script_transitions', 'customer_label')) {
                $table->dropColumn('customer_label');
            }
        });
    }
};
