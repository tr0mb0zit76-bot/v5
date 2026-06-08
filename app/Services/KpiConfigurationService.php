<?php

namespace App\Services;

use App\Models\KpiSetting;
use App\Support\KpiCustomerDeduction;
use Illuminate\Support\Facades\Schema;

class KpiConfigurationService
{
    public const BONUS_MULTIPLIER_KEY = 'delta_bonus_multiplier';

    public const VAT_PERCENT_KEY = 'vat_kpi_percent';

    public const VAT_ALL_PERCENT_KEY = 'vat_all_kpi_percent';

    public const CASHLESS_PERCENT_KEY = 'cashless_kpi_percent';

    public const VAT_ZERO_22_PERCENT_KEY = 'vat_zero_22_kpi_percent';

    public const VAT_ZERO_22_SUPPLEMENT_PERCENT_KEY = 'vat_zero_22_margin_supplement_percent';

    public const CASH_PRIMARY_PERCENT_KEY = 'cash_primary_kpi_percent';

    public const CASH_SECONDARY_PERCENT_KEY = 'cash_secondary_kpi_percent';

    public const DEFAULT_BONUS_MULTIPLIER = 1.3;

    public const DEFAULT_VAT_PERCENT = 3.0;

    public const DEFAULT_VAT_ALL_PERCENT = 4.0;

    public const DEFAULT_VAT_ZERO_22_PERCENT = 3.0;

    public const DEFAULT_VAT_ZERO_22_SUPPLEMENT_PERCENT = 15.0;

    public const DEFAULT_CASH_PRIMARY_PERCENT = 3.0;

    public const DEFAULT_CASH_SECONDARY_PERCENT = 21.0;

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
    }

    public function kpiDeductionAmount(float $customerRate, string $paymentCategory): float
    {
        return KpiCustomerDeduction::amount($customerRate, $paymentCategory, $this->deductionRates());
    }

    public function effectiveKpiPercent(float $customerRate, string $paymentCategory): float
    {
        return KpiCustomerDeduction::effectivePercent(
            $customerRate,
            $this->kpiDeductionAmount($customerRate, $paymentCategory),
        );
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
        ];
    }
}
