<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Строки допзатрат по подрядчикам в {@see FinancialTerm::$additional_costs}.
 *
 * @phpstan-type AdditionalCostRow array{
 *     id: string,
 *     contractor_id: int|null,
 *     contractor_name: string|null,
 *     service_date: string|null,
 *     amount: float|null,
 *     currency: string,
 *     payment_form: string|null,
 *     payment_schedule: array<string, mixed>,
 *     payment_terms: string
 * }
 */
final class OrderAdditionalCostNormalizer
{
    /**
     * @param  array<string, mixed>  $row
     * @return AdditionalCostRow
     */
    public static function normalizeRow(
        array $row,
        string $defaultCurrency = 'RUB',
        ?string $fallbackServiceDate = null,
    ): array {
        $serviceDate = filled($row['service_date'] ?? null)
            ? substr((string) $row['service_date'], 0, 10)
            : (filled($row['incurred_date'] ?? null)
                ? substr((string) $row['incurred_date'], 0, 10)
                : ($fallbackServiceDate !== null && $fallbackServiceDate !== ''
                    ? substr($fallbackServiceDate, 0, 10)
                    : null));

        $amount = $row['amount'] ?? null;
        $normalizedAmount = $amount !== null && $amount !== '' ? round((float) $amount, 2) : null;

        $contractorId = isset($row['contractor_id']) && $row['contractor_id'] !== null && $row['contractor_id'] !== ''
            ? (int) $row['contractor_id']
            : null;

        return [
            'id' => self::resolveRowId($row),
            'contractor_id' => $contractorId,
            'contractor_name' => filled($row['contractor_name'] ?? null)
                ? trim((string) $row['contractor_name'])
                : null,
            'service_date' => $serviceDate,
            'amount' => $normalizedAmount,
            'currency' => filled($row['currency'] ?? null)
                ? (string) $row['currency']
                : $defaultCurrency,
            'payment_form' => PaymentFormDictionary::normalizeForStorage($row['payment_form'] ?? null) ?? 'no_vat',
            'payment_schedule' => is_array($row['payment_schedule'] ?? null) ? $row['payment_schedule'] : [],
            'payment_terms' => trim((string) ($row['payment_terms'] ?? '')),
        ];
    }

    /**
     * @return list<AdditionalCostRow>
     */
    public static function normalizeList(
        mixed $rows,
        string $defaultCurrency = 'RUB',
        ?string $fallbackServiceDate = null,
    ): array {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized[] = self::normalizeRow($row, $defaultCurrency, $fallbackServiceDate);
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     * @return array{0: list<array<string, mixed>>, 1: list<AdditionalCostRow>}
     */
    public static function partitionContractorsCosts(
        array $contractorsCosts,
        string $defaultCurrency = 'RUB',
        ?string $fallbackServiceDate = null,
    ): array {
        $legCosts = [];
        $additionalCosts = [];

        foreach ($contractorsCosts as $cost) {
            if (! is_array($cost)) {
                continue;
            }

            if (ContractorCostRowClassification::isAdditional($cost)) {
                $additionalCosts[] = self::fromLegacyContractorCostRow($cost, $defaultCurrency, $fallbackServiceDate);

                continue;
            }

            unset($cost['is_additional'], $cost['incurred_date']);
            $legCosts[] = $cost;
        }

        return [$legCosts, $additionalCosts];
    }

    /**
     * @param  array<string, mixed>  $cost
     * @return AdditionalCostRow
     */
    public static function fromLegacyContractorCostRow(
        array $cost,
        string $defaultCurrency = 'RUB',
        ?string $fallbackServiceDate = null,
    ): array {
        return self::normalizeRow([
            'id' => $cost['id'] ?? null,
            'contractor_id' => $cost['contractor_id'] ?? null,
            'contractor_name' => $cost['contractor_name'] ?? null,
            'service_date' => $cost['incurred_date'] ?? $fallbackServiceDate,
            'amount' => $cost['amount'] ?? null,
            'currency' => $cost['currency'] ?? $defaultCurrency,
            'payment_form' => $cost['payment_form'] ?? null,
            'payment_schedule' => $cost['payment_schedule'] ?? [],
            'payment_terms' => $cost['payment_terms'] ?? '',
        ], $defaultCurrency, $fallbackServiceDate);
    }

    /**
     * @param  list<AdditionalCostRow>  $rows
     */
    public static function sumAmounts(array $rows): float
    {
        return round(collect($rows)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function resolveRowId(array $row): string
    {
        $existing = trim((string) ($row['id'] ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }
}
