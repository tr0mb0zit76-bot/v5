<?php

declare(strict_types=1);

namespace App\Services;

final class HowMuchCostsCalculatorService
{
    public function __construct(
        private readonly OwnFleetCostNormsService $normsService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     km_to_border: float,
     *     km_from_border: float,
     *     totals: array{
     *         cost_price: float,
     *         margin_percent: float,
     *         margin_absolute_rub: float,
     *         margin_from_percent_rub: float,
     *         customer_price: float
     *     },
     *     breakdown: list<array{label: string, amount: float}>,
     *     zones: array{
     *         cn: array{km: float, fuel: float, driver: float, other: float, subtotal: float},
     *         ru: array{km: float, fuel: float, driver: float, other: float, subtotal: float}
     *     },
     *     depreciation: float,
     *     norms: array<string, mixed>
     * }
     */
    public function calculate(array $input): array
    {
        $norms = $this->normsService->pagePayload();

        $kmToBorder = max(0.0, (float) ($input['km_to_border'] ?? 0));
        $kmFromBorder = max(0.0, (float) ($input['km_from_border'] ?? 0));

        $marginPercent = array_key_exists('margin_percent', $input) && $input['margin_percent'] !== null && $input['margin_percent'] !== ''
            ? max(0.0, (float) $input['margin_percent'])
            : (float) $norms['margin_percent'];

        $marginAbsolute = array_key_exists('margin_absolute_rub', $input) && $input['margin_absolute_rub'] !== null && $input['margin_absolute_rub'] !== ''
            ? max(0.0, (float) $input['margin_absolute_rub'])
            : (float) $norms['margin_absolute_rub'];

        $cn = $this->zoneTotals($kmToBorder, $norms['cn']);
        $ru = $this->zoneTotals($kmFromBorder, $norms['ru']);
        $depreciation = round(($kmToBorder + $kmFromBorder) * (float) $norms['depreciation_rub_per_km'], 2);

        $costPrice = round($cn['subtotal'] + $ru['subtotal'] + $depreciation, 2);
        $marginFromPercent = round($costPrice * $marginPercent / 100, 2);
        $customerPrice = round($costPrice + $marginFromPercent + $marginAbsolute, 2);

        return [
            'km_to_border' => $kmToBorder,
            'km_from_border' => $kmFromBorder,
            'totals' => [
                'cost_price' => $costPrice,
                'margin_percent' => $marginPercent,
                'margin_absolute_rub' => $marginAbsolute,
                'margin_from_percent_rub' => $marginFromPercent,
                'customer_price' => $customerPrice,
            ],
            'breakdown' => [
                ['label' => 'Топливо (Китай)', 'amount' => $cn['fuel']],
                ['label' => 'Труд водителя (Китай)', 'amount' => $cn['driver']],
                ['label' => 'Прочее (Китай)', 'amount' => $cn['other']],
                ['label' => 'Топливо (РФ)', 'amount' => $ru['fuel']],
                ['label' => 'Труд водителя (РФ)', 'amount' => $ru['driver']],
                ['label' => 'Прочее (РФ)', 'amount' => $ru['other']],
                ['label' => 'Амортизация', 'amount' => $depreciation],
                ['label' => 'Наценка %', 'amount' => $marginFromPercent],
                ['label' => 'Надбавка ₽', 'amount' => $marginAbsolute],
            ],
            'zones' => [
                'cn' => $cn,
                'ru' => $ru,
            ],
            'depreciation' => $depreciation,
            'norms' => $norms,
        ];
    }

    /**
     * @param  array{
     *     fuel_cost_rub_per_km: float,
     *     driver_rub_per_km: float,
     *     other_rub_per_km: float
     * }  $zoneNorms
     * @return array{km: float, fuel: float, driver: float, other: float, subtotal: float}
     */
    private function zoneTotals(float $km, array $zoneNorms): array
    {
        $fuel = round($km * (float) $zoneNorms['fuel_cost_rub_per_km'], 2);
        $driver = round($km * (float) $zoneNorms['driver_rub_per_km'], 2);
        $other = round($km * (float) $zoneNorms['other_rub_per_km'], 2);

        return [
            'km' => $km,
            'fuel' => $fuel,
            'driver' => $driver,
            'other' => $other,
            'subtotal' => round($fuel + $driver + $other, 2),
        ];
    }
}
