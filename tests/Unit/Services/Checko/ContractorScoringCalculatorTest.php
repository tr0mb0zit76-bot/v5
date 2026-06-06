<?php

namespace Tests\Unit\Services\Checko;

use App\Services\Checko\ContractorScoringCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorScoringCalculatorTest extends TestCase
{
    private ContractorScoringCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ContractorScoringCalculator;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function normalized(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'ООО Тест',
            'status_text' => 'Действующая',
            'finances_available' => true,
            'last_profit_positive' => true,
            'last_revenue_rub' => 10_000_000.0,
            'last_assets_rub' => null,
            'revenue_trend' => null,
            'company_age_years' => 6,
            'founders_count' => 1,
            'bankruptcy_risk_flag' => false,
            'enforcement_count' => 0,
            'enforcement_sum_rub' => 0.0,
            'defendant_cases' => 0,
            'plaintiff_cases' => 0,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function internal(array $overrides = []): array
    {
        return array_merge([
            'debt_limit_reached' => false,
            'stop_on_limit' => false,
            'current_debt' => 0.0,
            'debt_limit' => 1_000_000.0,
        ], $overrides);
    }

    #[Test]
    public function strong_profile_never_exceeds_ten_days_recommended_postpayment(): void
    {
        $result = $this->calculator->calculate(
            $this->normalized(['last_revenue_rub' => 50_000_000.0]),
            $this->internal(),
        );

        $this->assertGreaterThanOrEqual(78, $result['score']);
        $this->assertSame(10, $result['recommended_postpayment_days']);
        $this->assertLessThanOrEqual(ContractorScoringCalculator::MAX_RECOMMENDED_POSTPAYMENT_DAYS, $result['recommended_postpayment_days']);
        $this->assertSame('small', $result['tier']);
    }

    #[Test]
    public function enterprise_revenue_gets_higher_limit_than_micro_at_same_health(): void
    {
        $base = $this->normalized([
            'status_text' => 'Действующая',
            'finances_available' => true,
            'last_profit_positive' => true,
            'enforcement_count' => 0,
            'enforcement_sum_rub' => 0.0,
            'defendant_cases' => 0,
        ]);

        $micro = $this->calculator->calculate(
            $this->normalized(array_merge($base, ['last_revenue_rub' => 15_000_000.0])),
            $this->internal(),
        );

        $enterprise = $this->calculator->calculate(
            $this->normalized(array_merge($base, ['last_revenue_rub' => 6_000_000_000.0])),
            $this->internal(),
        );

        $this->assertSame('micro', $micro['tier']);
        $this->assertSame('enterprise', $enterprise['tier']);
        $this->assertGreaterThan($micro['recommended_debt_limit_rub'], $enterprise['recommended_debt_limit_rub']);
    }

    #[Test]
    public function liquidated_company_gets_zero_days(): void
    {
        $result = $this->calculator->calculate(
            $this->normalized([
                'status_text' => 'Ликвидирована',
                'finances_available' => false,
                'last_profit_positive' => null,
                'last_revenue_rub' => null,
            ]),
            $this->internal(),
        );

        $this->assertSame(0, $result['recommended_postpayment_days']);
        $this->assertSame(0, $result['recommended_debt_limit_rub']);
    }

    #[Test]
    public function debt_limit_reached_forces_zero_days(): void
    {
        $result = $this->calculator->calculate(
            $this->normalized(),
            $this->internal(['debt_limit_reached' => true, 'stop_on_limit' => true]),
        );

        $this->assertSame(0, $result['recommended_postpayment_days']);
        $this->assertSame(0, $result['recommended_debt_limit_rub']);
    }

    #[Test]
    public function company_active_matches_reference_cases(): void
    {
        $this->assertTrue($this->calculator->isCompanyActiveByEgrStatus('Действующая'));
        $this->assertTrue($this->calculator->isCompanyActiveByEgrStatus('Действует'));
        $this->assertFalse($this->calculator->isCompanyActiveByEgrStatus('Ликвидирована'));
        $this->assertFalse($this->calculator->isCompanyActiveByEgrStatus('В процессе ликвидации'));
        $this->assertFalse($this->calculator->isCompanyActiveByEgrStatus(''));
    }

    #[Test]
    public function activity_not_ceased_phrase_is_treated_as_active(): void
    {
        $this->assertTrue($this->calculator->isCompanyActiveByEgrStatus('Деятельность не прекращена'));
    }

    #[Test]
    public function api_meta_ok_is_not_active_company_status(): void
    {
        $this->assertFalse($this->calculator->isCompanyActiveByEgrStatus('ok'));
        $this->assertSame('unknown', $this->calculator->classifyEgrStatus('ok'));
    }

    #[Test]
    public function not_excluded_from_registry_phrase_is_active(): void
    {
        $this->assertTrue($this->calculator->isCompanyActiveByEgrStatus('Не исключён из реестра'));
    }

    #[Test]
    public function missing_status_is_unknown_not_inactive_so_score_is_not_capped_at_eighteen(): void
    {
        $result = $this->calculator->calculate(
            $this->normalized([
                'status_text' => null,
                'finances_available' => false,
                'last_profit_positive' => null,
                'last_revenue_rub' => null,
            ]),
            $this->internal(['debt_limit' => 500_000.0]),
        );

        $this->assertGreaterThan(32, $result['score']);
        $this->assertContains($result['grade'], ['B', 'C']);
        $this->assertGreaterThan(0, $result['recommended_postpayment_days']);
        $this->assertStringContainsString('финансовой отчётности', implode(' ', $result['factors']));
    }

    #[Test]
    public function nonstandard_status_phrase_without_deystv_is_unknown(): void
    {
        $this->assertSame('unknown', $this->calculator->classifyEgrStatus('Зарегистрировано'));
    }

    #[Test]
    public function calculate_includes_v2_component_fields(): void
    {
        $result = $this->calculator->calculate($this->normalized(), $this->internal());

        $this->assertArrayHasKey('tier', $result);
        $this->assertArrayHasKey('tier_label', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('legal', $result['components']);
        $this->assertArrayHasKey('scoring_model_version', $result);
    }
}
