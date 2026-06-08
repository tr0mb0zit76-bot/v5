<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('customer_payment_form', 64)->nullable();
            $table->boolean('customer_positive_vat_required')->default(false);
            $table->decimal('customer_vat_rate_percent', 5, 2)->nullable();
            $table->string('carrier_rule', 32);
            $table->json('carrier_payment_forms')->nullable();
            $table->decimal('carrier_vat_rate_percent', 5, 2)->nullable();
            $table->decimal('deduction_primary_percent', 6, 2)->default(0);
            $table->decimal('deduction_secondary_percent', 6, 2)->nullable();
            $table->decimal('margin_supplement_percent', 6, 2)->nullable();
            $table->decimal('margin_supplement_carrier_vat_percent', 5, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_from', 'effective_to'], 'kpi_deduction_rules_active_period_index');
            $table->index('priority', 'kpi_deduction_rules_priority_index');
        });

        $this->seedDefaultRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_deduction_rules');
    }

    private function seedDefaultRules(): void
    {
        if (! Schema::hasTable('kpi_deduction_rules')) {
            return;
        }

        $effectiveFrom = '2026-06-01';
        $now = now();

        $rows = [
            [
                'name' => 'Наличка',
                'priority' => 400,
                'customer_payment_form' => null,
                'customer_positive_vat_required' => false,
                'customer_vat_rate_percent' => null,
                'carrier_rule' => 'all_cash',
                'carrier_payment_forms' => null,
                'carrier_vat_rate_percent' => null,
                'deduction_primary_percent' => 3,
                'deduction_secondary_percent' => 21,
                'margin_supplement_percent' => null,
                'margin_supplement_carrier_vat_percent' => null,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'НДС 0% / 22%',
                'priority' => 300,
                'customer_payment_form' => null,
                'customer_positive_vat_required' => false,
                'customer_vat_rate_percent' => 0,
                'carrier_rule' => 'any_vat_rate',
                'carrier_payment_forms' => null,
                'carrier_vat_rate_percent' => 22,
                'deduction_primary_percent' => 3,
                'deduction_secondary_percent' => null,
                'margin_supplement_percent' => 15,
                'margin_supplement_carrier_vat_percent' => 22,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'НДС у всех',
                'priority' => 200,
                'customer_payment_form' => null,
                'customer_positive_vat_required' => true,
                'customer_vat_rate_percent' => null,
                'carrier_rule' => 'all_positive_vat',
                'carrier_payment_forms' => null,
                'carrier_vat_rate_percent' => null,
                'deduction_primary_percent' => 4,
                'deduction_secondary_percent' => null,
                'margin_supplement_percent' => null,
                'margin_supplement_carrier_vat_percent' => null,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Прочие НДС',
                'priority' => 100,
                'customer_payment_form' => null,
                'customer_positive_vat_required' => false,
                'customer_vat_rate_percent' => null,
                'carrier_rule' => 'any',
                'carrier_payment_forms' => null,
                'carrier_vat_rate_percent' => null,
                'deduction_primary_percent' => 3,
                'deduction_secondary_percent' => null,
                'margin_supplement_percent' => null,
                'margin_supplement_carrier_vat_percent' => null,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('kpi_deduction_rules')->insert($rows);
    }
};
