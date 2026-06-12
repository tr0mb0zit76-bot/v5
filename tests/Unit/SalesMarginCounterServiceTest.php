<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\SalesMarginCounterService;
use App\Support\KpiDeductionCarrierRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesMarginCounterServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('kpi_deduction_rules');

        Schema::create('kpi_deduction_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->string('customer_payment_form')->nullable();
            $table->boolean('customer_positive_vat_required')->default(false);
            $table->decimal('customer_vat_rate_percent', 5, 2)->nullable();
            $table->string('carrier_rule');
            $table->json('carrier_payment_forms')->nullable();
            $table->decimal('carrier_vat_rate_percent', 5, 2)->nullable();
            $table->decimal('deduction_primary_percent', 5, 2);
            $table->decimal('deduction_secondary_percent', 5, 2)->nullable();
            $table->decimal('margin_supplement_percent', 5, 2)->nullable();
            $table->decimal('margin_supplement_carrier_vat_percent', 5, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    #[Test]
    public function it_calculates_margin_for_selected_deduction_rule(): void
    {
        $rule = KpiDeductionRule::query()->create([
            'name' => 'Наличка у перевозчика',
            'priority' => 100,
            'customer_payment_form' => 'vat_22',
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
            'deduction_primary_percent' => 4,
            'deduction_secondary_percent' => 16,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $service = app(SalesMarginCounterService::class);

        $result = $service->calculate([
            'kpi_deduction_rule_id' => $rule->id,
            'customer_rate' => 100_000,
            'carrier_rate' => 80_000,
            'bonus' => 0,
            'additional_expenses' => 0,
        ]);

        $this->assertSame('4% + 16%', $result['summary']['kpi_deduction_rates_label']);
        $this->assertSame(19_360.0, $result['summary']['kpi_deduction_amount']);
        $this->assertSame(640.0, $result['summary']['margin']);
    }
}
