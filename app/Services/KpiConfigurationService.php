<?php

namespace App\Services;

use App\Models\KpiDeductionRule;
use App\Models\KpiSetting;
use App\Support\KpiCustomerDeduction;
use App\Support\KpiDeductionRuleAmount;
use App\Support\VatZeroCustomerStandardVatCarrierMarginSupplement;
use Illuminate\Support\Facades\Schema;

class KpiConfigurationService
{
    public const BONUS_MULTIPLIER_KEY = 'delta_bonus_multiplier';

    public const INSURANCE_MULTIPLIER_KEY = 'delta_insurance_multiplier';

    public const VAT_PERCENT_KEY = 'vat_kpi_percent';

    public const VAT_ALL_PERCENT_KEY = 'vat_all_kpi_percent';

    public const CASHLESS_PERCENT_KEY = 'cashless_kpi_percent';

    public const VAT_ZERO_22_PERCENT_KEY = 'vat_zero_22_kpi_percent';

    public const VAT_ZERO_22_SUPPLEMENT_PERCENT_KEY = 'vat_zero_22_margin_supplement_percent';

    public const CASH_PRIMARY_PERCENT_KEY = 'cash_primary_kpi_percent';

    public const CASH_SECONDARY_PERCENT_KEY = 'cash_secondary_kpi_percent';

    public const VAT_ZERO_CASH_PRIMARY_PERCENT_KEY = 'vat_zero_cash_primary_kpi_percent';

    public const VAT_ZERO_CASH_SECONDARY_PERCENT_KEY = 'vat_zero_cash_secondary_kpi_percent';

    public const DEFAULT_BONUS_MULTIPLIER = 1.3;

    public const DEFAULT_INSURANCE_MULTIPLIER = 1.2;

    public const DEFAULT_VAT_PERCENT = 3.0;

    public const DEFAULT_VAT_ALL_PERCENT = 4.0;

    public const DEFAULT_VAT_ZERO_22_PERCENT = 3.0;

    public const DEFAULT_VAT_ZERO_22_SUPPLEMENT_PERCENT = 15.0;

    public const DEFAULT_CASH_PRIMARY_PERCENT = 3.0;

    public const DEFAULT_CASH_SECONDARY_PERCENT = 21.0;

    public const DEFAULT_VAT_ZERO_CASH_PRIMARY_PERCENT = 4.0;

    public const DEFAULT_VAT_ZERO_CASH_SECONDARY_PERCENT = 16.0;

    public function getBonusMultiplier(): float
    {
        if (! Schema::hasTable('kpi_settings')) {
            return self::DEFAULT_BONUS_MULTIPLIER;
        }

        $value = KpiSetting::getValue(self::BONUS_MULTIPLIER_KEY, self::DEFAULT_BONUS_MULTIPLIER);

        return is_numeric($value) ? (float) $value : self::DEFAULT_BONUS_MULTIPLIER;
    }

    public function saveBonusMultiplier(float $value): void
    {
        KpiSetting::setValue(
            self::BONUS_MULTIPLIER_KEY,
            number_format($value, 2, '.', ''),
            'float',
            'delta',
            'Множитель бонуса в формуле delta',
        );
    }

    public function getInsuranceMultiplier(): float
    {
        if (! Schema::hasTable('kpi_settings')) {
            return self::DEFAULT_INSURANCE_MULTIPLIER;
        }

        $value = KpiSetting::getValue(self::INSURANCE_MULTIPLIER_KEY, self::DEFAULT_INSURANCE_MULTIPLIER);

        return is_numeric($value) ? (float) $value : self::DEFAULT_INSURANCE_MULTIPLIER;
    }

    public function saveInsuranceMultiplier(float $value): void
    {
        KpiSetting::setValue(
            self::INSURANCE_MULTIPLIER_KEY,
            number_format($value, 2, '.', ''),
            'float',
            'delta',
            'Множитель страховки в формуле delta',
        );
    }

    /**
     * @return array{
     *     vat_percent: float,
     *     vat_all_percent: float,
     *     vat_zero_22_percent: float,
     *     vat_zero_22_supplement_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     *     vat_zero_cash_primary_percent: float,
     *     vat_zero_cash_secondary_percent: float,
     * }
     */
    public function deductionRates(): array
    {
        if (! Schema::hasTable('kpi_settings')) {
            return $this->defaultDeductionRates();
        }

        return [
            'vat_percent' => $this->readVatPercentSetting(),
            'vat_all_percent' => $this->readPercentSetting(self::VAT_ALL_PERCENT_KEY, self::DEFAULT_VAT_ALL_PERCENT),
            'vat_zero_22_percent' => $this->readPercentSetting(self::VAT_ZERO_22_PERCENT_KEY, self::DEFAULT_VAT_ZERO_22_PERCENT),
            'vat_zero_22_supplement_percent' => $this->vatZero22MarginSupplementPercent(),
            'cash_primary_percent' => $this->readPercentSetting(self::CASH_PRIMARY_PERCENT_KEY, self::DEFAULT_CASH_PRIMARY_PERCENT),
            'cash_secondary_percent' => $this->readPercentSetting(self::CASH_SECONDARY_PERCENT_KEY, self::DEFAULT_CASH_SECONDARY_PERCENT),
            'vat_zero_cash_primary_percent' => $this->readPercentSetting(
                self::VAT_ZERO_CASH_PRIMARY_PERCENT_KEY,
                self::DEFAULT_VAT_ZERO_CASH_PRIMARY_PERCENT,
            ),
            'vat_zero_cash_secondary_percent' => $this->readPercentSetting(
                self::VAT_ZERO_CASH_SECONDARY_PERCENT_KEY,
                self::DEFAULT_VAT_ZERO_CASH_SECONDARY_PERCENT,
            ),
        ];
    }

    public function vatZero22MarginSupplementPercent(): float
    {
        if (! Schema::hasTable('kpi_settings')) {
            return self::DEFAULT_VAT_ZERO_22_SUPPLEMENT_PERCENT;
        }

        return $this->readPercentSetting(
            self::VAT_ZERO_22_SUPPLEMENT_PERCENT_KEY,
            self::DEFAULT_VAT_ZERO_22_SUPPLEMENT_PERCENT,
        );
    }

    /**
     * @param  array{
     *     vat_percent: float|int,
     *     vat_all_percent: float|int,
     *     vat_zero_22_percent: float|int,
     *     vat_zero_22_supplement_percent: float|int,
     *     cash_primary_percent: float|int,
     *     cash_secondary_percent: float|int,
     * }  $rates
     */
    public function saveDeductionRates(array $rates): void
    {
        KpiSetting::setValue(
            self::VAT_PERCENT_KEY,
            number_format((float) $rates['vat_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Вычет KPI для прочих сочетаний НДС, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::VAT_ALL_PERCENT_KEY,
            number_format((float) $rates['vat_all_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Вычет KPI при НДС у заказчика и у всех перевозчиков, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::VAT_ZERO_22_PERCENT_KEY,
            number_format((float) $rates['vat_zero_22_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Вычет KPI при НДС 0% у заказчика и 22% у перевозчика, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::VAT_ZERO_22_SUPPLEMENT_PERCENT_KEY,
            number_format((float) $rates['vat_zero_22_supplement_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Доплата к марже при НДС 0% / 22%, % от суммы перевозчиков с НДС 22%',
        );
        KpiSetting::setValue(
            self::CASH_PRIMARY_PERCENT_KEY,
            number_format((float) $rates['cash_primary_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Первый вычет KPI для налички, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::CASH_SECONDARY_PERCENT_KEY,
            number_format((float) $rates['cash_secondary_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Второй вычет KPI для налички, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::VAT_ZERO_CASH_PRIMARY_PERCENT_KEY,
            number_format((float) $rates['vat_zero_cash_primary_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Первый вычет KPI при НДС 0% у заказчика и наличных у перевозчика, % от суммы заказчика',
        );
        KpiSetting::setValue(
            self::VAT_ZERO_CASH_SECONDARY_PERCENT_KEY,
            number_format((float) $rates['vat_zero_cash_secondary_percent'], 2, '.', ''),
            'float',
            'kpi',
            'Второй вычет KPI при НДС 0% / наличные, % от остатка после первого вычета',
        );
    }

    public function kpiDeductionAmount(float $customerRate, string $paymentCategory): float
    {
        return KpiCustomerDeduction::amount($customerRate, $paymentCategory, $this->deductionRates());
    }

    /**
     * @param  array{
     *     deal_type: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }  $dealResolution
     */
    public function kpiDeductionAmountForResolution(float $customerRate, array $dealResolution): float
    {
        $rule = $dealResolution['rule'] ?? null;

        if ($rule instanceof KpiDeductionRule) {
            return KpiDeductionRuleAmount::deductionAmount($rule, $customerRate);
        }

        if (($dealResolution['uses_custom_rules'] ?? false) && ($dealResolution['deal_type'] ?? '') === 'unknown') {
            return 0.0;
        }

        return $this->kpiDeductionAmount($customerRate, (string) ($dealResolution['deal_type'] ?? 'unknown'));
    }

    public function effectiveKpiPercent(float $customerRate, string $paymentCategory): float
    {
        return KpiCustomerDeduction::effectivePercent(
            $customerRate,
            $this->kpiDeductionAmount($customerRate, $paymentCategory),
        );
    }

    /**
     * @param  array{
     *     deal_type: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }  $dealResolution
     */
    public function effectiveKpiPercentForResolution(float $customerRate, array $dealResolution): float
    {
        return KpiDeductionRuleAmount::effectivePercent(
            $customerRate,
            $this->kpiDeductionAmountForResolution($customerRate, $dealResolution),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     * @param  array{
     *     deal_type: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }  $dealResolution
     */
    public function marginSupplementForResolution(
        array $dealResolution,
        ?string $customerPaymentForm,
        array $contractorsCosts,
    ): float {
        $rule = $dealResolution['rule'] ?? null;

        if ($rule instanceof KpiDeductionRule) {
            return KpiDeductionRuleAmount::marginSupplementAmount($rule, $customerPaymentForm, $contractorsCosts);
        }

        if (($dealResolution['deal_type'] ?? '') !== 'vat_zero_22') {
            return 0.0;
        }

        return VatZeroCustomerStandardVatCarrierMarginSupplement::amount(
            $customerPaymentForm,
            $contractorsCosts,
            $this->vatZero22MarginSupplementPercent(),
        );
    }

    /**
     * @param  array{
     *     deal_type: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }  $dealResolution
     */
    public function deductionRatesLabelForResolution(array $dealResolution): string
    {
        $rule = $dealResolution['rule'] ?? null;

        if ($rule instanceof KpiDeductionRule) {
            return KpiDeductionRuleAmount::ratesLabel($rule);
        }

        return $this->deductionRatesLabel((string) ($dealResolution['deal_type'] ?? 'unknown'));
    }

    public function deductionRatesLabel(string $paymentCategory): string
    {
        $rates = $this->deductionRates();

        if ($paymentCategory === 'cash') {
            return sprintf(
                '%s%% + %s%%',
                $this->formatPercent((float) $rates['cash_primary_percent']),
                $this->formatPercent((float) $rates['cash_secondary_percent']),
            );
        }

        if ($paymentCategory === 'vat_zero_cash') {
            return sprintf(
                '%s%% + %s%%',
                $this->formatPercent((float) $rates['vat_zero_cash_primary_percent']),
                $this->formatPercent((float) $rates['vat_zero_cash_secondary_percent']),
            );
        }

        if ($paymentCategory === 'vat_zero_22') {
            return sprintf('%s%%', $this->formatPercent((float) $rates['vat_zero_22_percent']));
        }

        if ($paymentCategory === 'vat_all') {
            return sprintf('%s%%', $this->formatPercent((float) $rates['vat_all_percent']));
        }

        if (in_array($paymentCategory, ['vat', 'cashless'], true)) {
            return sprintf('%s%%', $this->formatPercent((float) $rates['vat_percent']));
        }

        return '—';
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function readVatPercentSetting(): float
    {
        $vat = KpiSetting::getValue(self::VAT_PERCENT_KEY, null);

        if (is_numeric($vat)) {
            return (float) $vat;
        }

        return $this->readPercentSetting(self::CASHLESS_PERCENT_KEY, self::DEFAULT_VAT_PERCENT);
    }

    private function readPercentSetting(string $key, float $default): float
    {
        $value = KpiSetting::getValue($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array{
     *     vat_percent: float,
     *     vat_all_percent: float,
     *     vat_zero_22_percent: float,
     *     vat_zero_22_supplement_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     * }
     */
    private function defaultDeductionRates(): array
    {
        return [
            'vat_percent' => self::DEFAULT_VAT_PERCENT,
            'vat_all_percent' => self::DEFAULT_VAT_ALL_PERCENT,
            'vat_zero_22_percent' => self::DEFAULT_VAT_ZERO_22_PERCENT,
            'vat_zero_22_supplement_percent' => self::DEFAULT_VAT_ZERO_22_SUPPLEMENT_PERCENT,
            'cash_primary_percent' => self::DEFAULT_CASH_PRIMARY_PERCENT,
            'cash_secondary_percent' => self::DEFAULT_CASH_SECONDARY_PERCENT,
            'vat_zero_cash_primary_percent' => self::DEFAULT_VAT_ZERO_CASH_PRIMARY_PERCENT,
            'vat_zero_cash_secondary_percent' => self::DEFAULT_VAT_ZERO_CASH_SECONDARY_PERCENT,
        ];
    }
}
