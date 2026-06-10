<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Разделение строк {@see financial_terms.contractors_costs}: оплата перевозчикам по плечам vs доп. исполнители.
 */
final class ContractorCostRowClassification
{
    /**
     * @param  array<string, mixed>  $cost
     */
    public static function isAdditional(array $cost): bool
    {
        if (! empty($cost['is_additional'])) {
            return true;
        }

        return self::isAdditionalStage((string) ($cost['stage'] ?? ''));
    }

    public static function isAdditionalStage(string $stage): bool
    {
        if ($stage === 'additional') {
            return true;
        }

        return preg_match('/^additional_\d+$/', $stage) === 1;
    }

    /**
     * Строки оплаты перевозчикам по плечам (без доп. исполнителей из contractors_costs).
     *
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    public static function carrierLegCostsOnly(array $costs): array
    {
        return array_values(array_filter(
            $costs,
            fn (array $cost): bool => ! self::isAdditional($cost),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $costs
     */
    public static function nextAdditionalStage(array $costs): string
    {
        $index = 1;

        while (collect($costs)->contains(
            fn (array $cost): bool => (string) ($cost['stage'] ?? '') === "additional_{$index}",
        )) {
            $index++;
        }

        return "additional_{$index}";
    }
}
