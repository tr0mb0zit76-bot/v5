<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\KpiDeductionRuleResolver;
use App\Support\KpiDeductionCarrierRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiDeductionRuleResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('kpi_deduction_rules');

        Schema::create('kpi_deduction_rules', function (Blueprint $table): void {
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
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kpi_deduction_rules');

        parent::tearDown();
    }

    #[Test]
    public function it_uses_legacy_categories_before_cutoff_date(): void
    {
        $resolver = app(KpiDeductionRuleResolver::class);

        $result = $resolver->resolve('2026-05-31', 'vat_22', ['cash']);

        $this->assertFalse($result['uses_custom_rules']);
        $this->assertSame('cash', $result['deal_type']);
    }

    #[Test]
    public function it_uses_custom_rules_on_and_after_cutoff_date(): void
    {
        KpiDeductionRule::query()->create([
            'name' => 'Наличка',
            'priority' => 400,
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
            'deduction_primary_percent' => 3,
            'deduction_secondary_percent' => 21,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $resolver = app(KpiDeductionRuleResolver::class);
        $result = $resolver->resolve('2026-06-01', 'vat_22', ['cash']);

        $this->assertTrue($result['uses_custom_rules']);
        $this->assertStringStartsWith('rule:', $result['deal_type']);
        $this->assertSame('Наличка', $result['deal_type_label']);
    }

    #[Test]
    public function it_returns_unknown_when_no_custom_rule_matches(): void
    {
        $resolver = app(KpiDeductionRuleResolver::class);
        $result = $resolver->resolve('2026-06-01', 'vat_22', ['vat_22']);

        $this->assertTrue($result['uses_custom_rules']);
        $this->assertSame('unknown', $result['deal_type']);
    }
}
