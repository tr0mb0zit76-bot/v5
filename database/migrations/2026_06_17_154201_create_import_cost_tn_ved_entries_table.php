<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_cost_tn_ved_entries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('code_display', 12);
            $table->string('label');
            $table->decimal('duty_percent', 8, 4)->default(0);
            $table->decimal('vat_percent', 8, 4)->default(22);
            $table->string('pp1291_category_key', 64)->nullable()->index();
            $table->boolean('requires_utilization_fee')->default(true);
            $table->string('duty_source', 32)->default('config');
            $table->json('eec_payload')->nullable();
            $table->timestamp('eec_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_cost_tn_ved_entries');
    }
};
