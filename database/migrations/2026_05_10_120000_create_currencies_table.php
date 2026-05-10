<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('currencies')) {
            return;
        }

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('currencies')->insert([
            ['code' => 'RUB', 'name' => 'Российский рубль', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'Доллар США', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CNY', 'name' => 'Китайский юань', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Евро', 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
