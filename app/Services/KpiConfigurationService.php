<?php

namespace App\Services;

use App\Models\KpiSetting;
use App\Models\KpiThreshold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiConfigurationService
{
    public const BONUS_MULTIPLIER_KEY = 'delta_bonus_multiplier';

    public const DEFAULT_BONUS_MULTIPLIER = 1.3;

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
     * @return array<int, array<string, mixed>>
     */
    public function groupedThresholds(): array
    {
        if (! Schema::hasTable('kpi_thresholds')) {
            return $this->defaultThresholdRows();
        }

        $thresholds = KpiThreshold::active();

        if ($thresholds->isEmpty()) {
            return $this->defaultThresholdRows();
        }

        return $thresholds
            ->groupBy(fn (KpiThreshold $threshold): string => $threshold->threshold_from.'|'.$threshold->threshold_to)
            ->map(function (Collection $group): array {
                /** @var KpiThreshold|null $direct */
                $direct = $group->firstWhere('deal_type', 'direct');
                /** @var KpiThreshold|null $indirect */
                $indirect = $group->firstWhere('deal_type', 'indirect');

                return [
                    'threshold_from' => (float) ($direct?->threshold_from ?? $indirect?->threshold_from ?? 0),
                    'threshold_to' => (float) ($direct?->threshold_to ?? $indirect?->threshold_to ?? 0),
                    'direct_kpi' => (int) ($direct?->kpi_percent ?? 0),
                    'indirect_kpi' => (int) ($indirect?->kpi_percent ?? 0),
                ];
            })
            ->sortBy('threshold_from')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceThresholds(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            KpiThreshold::query()->delete();

            $payload = collect($rows)
                ->flatMap(fn (array $row): array => [
                    [
                        'deal_type' => 'direct',
                        'threshold_from' => $row['threshold_from'],
                        'threshold_to' => $row['threshold_to'],
                        'kpi_percent' => $row['direct_kpi'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'deal_type' => 'indirect',
                        'threshold_from' => $row['threshold_from'],
                        'threshold_to' => $row['threshold_to'],
                        'kpi_percent' => $row['indirect_kpi'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ])
                ->all();

            if ($payload !== []) {
                KpiThreshold::query()->insert($payload);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultThresholdRows(): array
    {
        return [
            ['threshold_from' => 0.00, 'threshold_to' => 0.24, 'direct_kpi' => 3, 'indirect_kpi' => 7],
            ['threshold_from' => 0.25, 'threshold_to' => 0.49, 'direct_kpi' => 4, 'indirect_kpi' => 8],
            ['threshold_from' => 0.50, 'threshold_to' => 1.00, 'direct_kpi' => 5, 'indirect_kpi' => 9],
        ];
    }
}
