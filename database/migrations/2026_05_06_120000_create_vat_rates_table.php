<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vat_rates')) {
            return;
        }

        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label');
            $table->decimal('rate_percent', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('vat_rates')->insert([
            [
                'code' => 'vat_22',
                'label' => 'С НДС 22%',
                'rate_percent' => 22,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'vat_5',
                'label' => 'С НДС 5%',
                'rate_percent' => 5,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'vat_0',
                'label' => 'С НДС 0%',
                'rate_percent' => 0,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};
