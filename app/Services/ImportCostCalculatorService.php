<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ImportCostReferenceMeta;
use App\Support\ImportCostTnVedCatalog;
use App\Support\UtilizationFeeCatalog;

final class ImportCostCalculatorService
{
    /**
     * @return array<string, mixed>
     */
    public function pagePayload(): array
    {
        return [
            'currencies' => config('import_cost_calculator.currencies', []),
            'disclaimer' => (string) config('import_cost_calculator.disclaimer', ''),
            'defaultVatPercent' => (float) config('import_cost_calculator.default_vat_percent', 22),
            'referenceMeta' => ImportCostReferenceMeta::forUi(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $tnVedCode = ImportCostTnVedCatalog::normalizeCode((string) ($input['tn_ved_code'] ?? ''));
        $tnVed = ImportCostTnVedCatalog::find($tnVedCode);

        if ($tnVed === null) {
            return ['error' => 'Выберите код ТН ВЭД из справочника.'];
        }

        $invoiceAmount = $this->positiveAmount($input['invoice_amount'] ?? null);
        if ($invoiceAmount === null) {
            return ['warning' => 'Укажите инвойсную стоимость.'];
        }

        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));
        $exchangeRate = $currency === 'RUB'
            ? 1.0
            : $this->positiveAmount($input['exchange_rate'] ?? null);

        if ($exchangeRate === null) {
            return ['warning' => 'Укажите курс валюты к рублю.'];
        }

        $invoiceRub = round($invoiceAmount * $exchangeRate, 0);
        $freightToBorder = round(max(0.0, (float) ($input['freight_to_border'] ?? 0)), 0);
        $freightAfterBorder = round(max(0.0, (float) ($input['freight_after_border'] ?? 0)), 0);
        $otherCosts = round(max(0.0, (float) ($input['other_costs'] ?? 0)), 0);
        $vehicleAgeYears = max(0, (int) ($input['vehicle_age_years'] ?? 0));
        $includeUtilizationFee = (bool) ($input['include_utilization_fee'] ?? true);

        $dutyPercent = array_key_exists('duty_percent_override', $input) && $input['duty_percent_override'] !== null
            ? max(0.0, (float) $input['duty_percent_override'])
            : (float) $tnVed['duty_percent'];

        $vatPercent = array_key_exists('vat_percent_override', $input) && $input['vat_percent_override'] !== null
            ? max(0.0, (float) $input['vat_percent_override'])
            : (float) $tnVed['vat_percent'];

        $customsValue = round($invoiceRub + $freightToBorder, 0);
        $dutyAmount = round($customsValue * $dutyPercent / 100, 0);
        $vatBase = round($customsValue + $dutyAmount, 0);
        $vatAmount = round($vatBase * $vatPercent / 100, 0);
        $customsProcessingFee = round($this->customsProcessingFee($customsValue), 0);

        $utilizationFee = 0;
        $utilizationMeta = null;
        $categoryKey = $tnVed['pp1291_category_key'] ?? $tnVed['utilization_profile'] ?? null;

        if ($includeUtilizationFee && $tnVed['requires_utilization_fee'] && filled($categoryKey)) {
            $utilizationMeta = UtilizationFeeCatalog::feeForCategory((string) $categoryKey, $vehicleAgeYears);
            $utilizationFee = (int) ($utilizationMeta['fee_rub'] ?? 0);
        }

        $totalLanded = round(
            $customsValue
            + $dutyAmount
            + $vatAmount
            + $customsProcessingFee
            + $utilizationFee
            + $freightAfterBorder
            + $otherCosts,
            0,
        );

        $referenceMeta = ImportCostReferenceMeta::forUi();

        $breakdown = [
            [
                'key' => 'invoice_rub',
                'label' => 'Инвойс в рублях',
                'amount' => $invoiceRub,
                'meta' => $currency === 'RUB'
                    ? null
                    : sprintf('%s %s × %s ₽', number_format($invoiceAmount, 2, '.', ' '), $currency, rtrim(rtrim(number_format($exchangeRate, 4, '.', ' '), '0'), '.')),
            ],
            [
                'key' => 'freight_to_border',
                'label' => 'Доставка до границы / в таможенную стоимость',
                'amount' => $freightToBorder,
                'meta' => null,
            ],
            [
                'key' => 'customs_value',
                'label' => 'Таможенная стоимость',
                'amount' => $customsValue,
                'meta' => 'инвойс + доставка до границы',
            ],
            [
                'key' => 'duty',
                'label' => 'Таможенная пошлина',
                'amount' => $dutyAmount,
                'meta' => $dutyPercent.'% · источник: '.($tnVed['duty_source_label'] ?? $tnVed['duty_source'] ?? 'config'),
            ],
            [
                'key' => 'vat',
                'label' => 'НДС при ввозе',
                'amount' => $vatAmount,
                'meta' => $vatPercent.'% от (таможенная стоимость + пошлина)',
            ],
            [
                'key' => 'customs_processing_fee',
                'label' => 'Таможенный сбор за оформление',
                'amount' => $customsProcessingFee,
                'meta' => null,
            ],
        ];

        if ($utilizationFee > 0 && $utilizationMeta !== null) {
            $breakdown[] = [
                'key' => 'utilization_fee',
                'label' => 'Утильсбор (ПП № 1291)',
                'amount' => $utilizationFee,
                'meta' => sprintf(
                    '%s · %s · база %s ₽ × %s',
                    $utilizationMeta['label'],
                    $utilizationMeta['age_bracket_label'],
                    number_format((int) $utilizationMeta['base_fee_rub'], 0, '.', ' '),
                    rtrim(rtrim(number_format((float) $utilizationMeta['coefficient'], 4, '.', ''), '0'), '.'),
                ),
            ];
        } elseif ($includeUtilizationFee && $tnVed['requires_utilization_fee']) {
            $breakdown[] = [
                'key' => 'utilization_fee',
                'label' => 'Утильсбор',
                'amount' => 0,
                'meta' => 'категория ПП № 1291 не найдена — выполните import-cost:sync-references',
            ];
        }

        if ($freightAfterBorder > 0) {
            $breakdown[] = [
                'key' => 'freight_after_border',
                'label' => 'Доставка после выпуска',
                'amount' => $freightAfterBorder,
                'meta' => null,
            ];
        }

        if ($otherCosts > 0) {
            $breakdown[] = [
                'key' => 'other_costs',
                'label' => 'Прочие расходы (брокер, СВХ и т.д.)',
                'amount' => $otherCosts,
                'meta' => null,
            ];
        }

        return [
            'summary' => [
                'total_landed' => $totalLanded,
                'customs_value' => $customsValue,
                'duty_percent' => $dutyPercent,
                'vat_percent' => $vatPercent,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'tn_ved' => [
                    'code' => $tnVed['code'],
                    'code_display' => $tnVed['code_display'],
                    'label' => $tnVed['label'],
                ],
                'vehicle_age_years' => $vehicleAgeYears,
                'disclaimer' => (string) config('import_cost_calculator.disclaimer', ''),
                'reference_meta' => $referenceMeta,
            ],
            'breakdown' => $breakdown,
        ];
    }

    private function customsProcessingFee(float $customsValue): float
    {
        $tiers = config('import_cost_calculator.customs_processing_fee_tiers', []);

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $max = (float) ($tier['max'] ?? 0);
            if ($customsValue <= $max) {
                return (float) ($tier['fee'] ?? 0);
            }
        }

        return 0.0;
    }

    private function positiveAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = is_numeric($value) ? (float) $value : null;

        if ($numeric === null || ! is_finite($numeric) || $numeric <= 0) {
            return null;
        }

        return $numeric;
    }
}
