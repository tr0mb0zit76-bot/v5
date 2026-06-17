<?php

namespace App\Support;

use App\Models\ImportCostPp1291Category;
use Illuminate\Support\Facades\Schema;

final class UtilizationFeeCatalog
{
    /**
     * @return array{
     *     label: string,
     *     fee_rub: int,
     *     age_bracket_label: string,
     *     base_fee_rub: int,
     *     coefficient: float,
     *     decree_reference: string
     * }|null
     */
    public static function feeForCategory(string $categoryKey, int $vehicleAgeYears): ?array
    {
        if (Schema::hasTable('import_cost_pp1291_categories')) {
            $category = ImportCostPp1291Category::query()->where('key', $categoryKey)->first();
            if ($category !== null) {
                return self::feeFromCategory(
                    $category->name,
                    (int) $category->base_fee_rub,
                    $category->age_coefficients ?? [],
                    $vehicleAgeYears,
                    (string) $category->decree_reference,
                );
            }
        }

        return self::feeFromConfigProfile($categoryKey, $vehicleAgeYears);
    }

    /**
     * @deprecated Используйте feeForCategory()
     *
     * @return array{label: string, fee_rub: int, age_bracket_label: string}|null
     */
    public static function feeForProfile(string $profileKey, int $vehicleAgeYears): ?array
    {
        $result = self::feeForCategory($profileKey, $vehicleAgeYears);

        if ($result === null) {
            return null;
        }

        return [
            'label' => $result['label'],
            'fee_rub' => $result['fee_rub'],
            'age_bracket_label' => $result['age_bracket_label'],
        ];
    }

    /**
     * @param  list<array{max_age_years?: int, coefficient?: float, fee_rub?: int}>  $ageCoefficients
     * @return array{
     *     label: string,
     *     fee_rub: int,
     *     age_bracket_label: string,
     *     base_fee_rub: int,
     *     coefficient: float,
     *     decree_reference: string
     * }
     */
    private static function feeFromCategory(
        string $name,
        int $baseFeeRub,
        array $ageCoefficients,
        int $vehicleAgeYears,
        string $decreeReference,
    ): ?array {
        $ageYears = max(0, $vehicleAgeYears);
        $coefficient = null;
        $bracketLabel = '';

        foreach ($ageCoefficients as $bracket) {
            if (! is_array($bracket)) {
                continue;
            }

            $maxAge = (int) ($bracket['max_age_years'] ?? PHP_INT_MAX);

            if (array_key_exists('coefficient', $bracket)) {
                $coefficient = (float) $bracket['coefficient'];
                $bracketLabel = self::bracketLabel($maxAge);
            } elseif (array_key_exists('fee_rub', $bracket)) {
                $coefficient = ((int) $bracket['fee_rub']) / max(1, $baseFeeRub);
                $bracketLabel = self::bracketLabel($maxAge);
            }

            if ($ageYears <= $maxAge) {
                break;
            }
        }

        if ($coefficient === null) {
            return null;
        }

        $feeRub = (int) round($baseFeeRub * $coefficient, 0);

        return [
            'label' => $name,
            'fee_rub' => $feeRub,
            'age_bracket_label' => $bracketLabel,
            'base_fee_rub' => $baseFeeRub,
            'coefficient' => $coefficient,
            'decree_reference' => $decreeReference,
        ];
    }

    /**
     * @return array{label: string, fee_rub: int, age_bracket_label: string}|null
     */
    private static function feeFromConfigProfile(string $profileKey, int $vehicleAgeYears): ?array
    {
        $profiles = config('import_cost_calculator.utilization_profiles', []);
        $profile = $profiles[$profileKey] ?? null;

        if (! is_array($profile)) {
            return null;
        }

        $result = self::feeFromCategory(
            (string) ($profile['label'] ?? $profileKey),
            150_000,
            $profile['fees_by_age'] ?? [],
            $vehicleAgeYears,
            'ПП РФ № 1291 (config)',
        );

        if ($result === null) {
            return null;
        }

        return [
            'label' => $result['label'],
            'fee_rub' => $result['fee_rub'],
            'age_bracket_label' => $result['age_bracket_label'],
        ];
    }

    private static function bracketLabel(int $maxAge): string
    {
        return match (true) {
            $maxAge <= 3 => 'до 3 лет',
            $maxAge <= 5 => '3–5 лет',
            $maxAge <= 7 => '5–7 лет',
            default => 'старше 7 лет',
        };
    }
}
