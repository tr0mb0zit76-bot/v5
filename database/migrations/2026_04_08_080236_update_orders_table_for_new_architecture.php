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
        Schema::table('orders', function (Blueprint $table) {
            // Удаляем старые поля, которые теперь хранятся в отдельных таблицах
            $table->dropColumn([
                'performers',           // Теперь в leg_contractor_assignments
                'payment_terms',        // Теперь в leg_costs
                'carrier_rate',         // Теперь в leg_costs
                'carrier_payment_form', // Теперь в leg_costs
                'carrier_payment_term', // Теперь в leg_costs
            ]);

            // Делаем carrier_id nullable, так как теперь исполнители хранятся на уровне плечей
            $table->foreignId('carrier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Восстанавливаем удаленные поля
            $table->json('performers')->nullable()->after('metadata');
            $table->text('payment_terms')->nullable()->after('special_notes');
            $table->decimal('carrier_rate', 10, 2)->nullable()->after('customer_rate');
            $table->string('carrier_payment_form')->nullable()->after('customer_payment_term');
            $table->string('carrier_payment_term')->nullable()->after('carrier_payment_form');

            // Восстанавливаем carrier_id как NOT NULL
            $table->foreignId('carrier_id')->nullable(false)->change();
        });
    }
};
