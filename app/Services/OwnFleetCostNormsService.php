<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OwnFleetCostNorm;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class OwnFleetCostNormsService
{
    /**
     * @return array{
     *     cn: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     ru: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     depreciation_rub_per_km: float,
     *     margin_percent: float,
     *     margin_absolute_rub: float,
     *     updated_at: string|null
     * }
     */
    public function pagePayload(): array
    {
        return $this->toPayload($this->current());
    }

    public function current(): OwnFleetCostNorm
    {
        if (! Schema::hasTable('own_fleet_cost_norms')) {
            return new OwnFleetCostNorm($this->defaultAttributes());
        }

        $existing = OwnFleetCostNorm::query()->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return OwnFleetCostNorm::query()->create($this->defaultAttributes());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     cn: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     ru: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     depreciation_rub_per_km: float,
     *     margin_percent: float,
     *     margin_absolute_rub: float,
     *     updated_at: string|null
     * }
     */
    public function update(array $input, ?User $actor = null): array
    {
        $norm = $this->current();

        $norm->fill([
            'cn_fuel_price_rub_per_liter' => $this->nonNegative($input['cn']['fuel_price_rub_per_liter'] ?? 0),
            'cn_fuel_consumption_l_per_100km' => $this->nonNegative($input['cn']['fuel_consumption_l_per_100km'] ?? 0),
            'cn_driver_rub_per_km' => $this->nonNegative($input['cn']['driver_rub_per_km'] ?? 0),
            'cn_other_rub_per_km' => $this->nonNegative($input['cn']['other_rub_per_km'] ?? 0),
            'ru_fuel_price_rub_per_liter' => $this->nonNegative($input['ru']['fuel_price_rub_per_liter'] ?? 0),
            'ru_fuel_consumption_l_per_100km' => $this->nonNegative($input['ru']['fuel_consumption_l_per_100km'] ?? 0),
            'ru_driver_rub_per_km' => $this->nonNegative($input['ru']['driver_rub_per_km'] ?? 0),
            'ru_other_rub_per_km' => $this->nonNegative($input['ru']['other_rub_per_km'] ?? 0),
            'depreciation_rub_per_km' => $this->nonNegative($input['depreciation_rub_per_km'] ?? 0),
            'margin_percent' => $this->nonNegative($input['margin_percent'] ?? 0),
            'margin_absolute_rub' => $this->nonNegative($input['margin_absolute_rub'] ?? 0),
            'updated_by' => $actor?->id,
        ]);

        $norm->save();

        return $this->toPayload($norm->fresh());
    }

    public function fuelCostRubPerKm(float $priceRubPerLiter, float $consumptionLPer100Km): float
    {
        return round(($consumptionLPer100Km / 100.0) * $priceRubPerLiter, 4);
    }

    /**
     * @return array{
     *     cn: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     ru: array{
     *         fuel_price_rub_per_liter: float,
     *         fuel_consumption_l_per_100km: float,
     *         fuel_cost_rub_per_km: float,
     *         driver_rub_per_km: float,
     *         other_rub_per_km: float
     *     },
     *     depreciation_rub_per_km: float,
     *     margin_percent: float,
     *     margin_absolute_rub: float,
     *     updated_at: string|null
     * }
     */
    public function toPayload(OwnFleetCostNorm $norm): array
    {
        $cnPrice = (float) $norm->cn_fuel_price_rub_per_liter;
        $cnConsumption = (float) $norm->cn_fuel_consumption_l_per_100km;
        $ruPrice = (float) $norm->ru_fuel_price_rub_per_liter;
        $ruConsumption = (float) $norm->ru_fuel_consumption_l_per_100km;

        return [
            'cn' => [
                'fuel_price_rub_per_liter' => $cnPrice,
                'fuel_consumption_l_per_100km' => $cnConsumption,
                'fuel_cost_rub_per_km' => $this->fuelCostRubPerKm($cnPrice, $cnConsumption),
                'driver_rub_per_km' => (float) $norm->cn_driver_rub_per_km,
                'other_rub_per_km' => (float) $norm->cn_other_rub_per_km,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => $ruPrice,
                'fuel_consumption_l_per_100km' => $ruConsumption,
                'fuel_cost_rub_per_km' => $this->fuelCostRubPerKm($ruPrice, $ruConsumption),
                'driver_rub_per_km' => (float) $norm->ru_driver_rub_per_km,
                'other_rub_per_km' => (float) $norm->ru_other_rub_per_km,
            ],
            'depreciation_rub_per_km' => (float) $norm->depreciation_rub_per_km,
            'margin_percent' => (float) $norm->margin_percent,
            'margin_absolute_rub' => (float) $norm->margin_absolute_rub,
            'updated_at' => $norm->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function defaultAttributes(): array
    {
        return [
            'cn_fuel_price_rub_per_liter' => 0,
            'cn_fuel_consumption_l_per_100km' => 0,
            'cn_driver_rub_per_km' => 0,
            'cn_other_rub_per_km' => 0,
            'ru_fuel_price_rub_per_liter' => 0,
            'ru_fuel_consumption_l_per_100km' => 0,
            'ru_driver_rub_per_km' => 0,
            'ru_other_rub_per_km' => 0,
            'depreciation_rub_per_km' => 0,
            'margin_percent' => 0,
            'margin_absolute_rub' => 0,
            'updated_by' => null,
        ];
    }

    private function nonNegative(mixed $value): float
    {
        return max(0.0, (float) $value);
    }
}
