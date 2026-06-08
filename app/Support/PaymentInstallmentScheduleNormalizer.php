<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Нормализация JSON графика с массивом траншей: проценты, суммы, округление последней транши.
 */
final class PaymentInstallmentScheduleNormalizer
{
    public const MAX_INSTALLMENTS = 10;

    /**
     * @param  array<string, mixed>  $schedule
     */
    public static function isInstallmentModel(array $schedule): bool
    {
        $list = $schedule['installments'] ?? null;

        return is_array($list) && $list !== [];
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public static function ensureInstallmentModel(array $schedule): array
    {
        if (self::isInstallmentModel($schedule)) {
            return $schedule;
        }

        return PaymentScheduleLegacyConverter::toInstallments($schedule);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public static function normalize(array $schedule, float $totalAmount): array
    {
        $schedule = self::ensureInstallmentModel($schedule);

        if (! self::isInstallmentModel($schedule)) {
            return $schedule;
        }

        $list = array_values(array_filter($schedule['installments'], static fn ($row): bool => is_array($row)));
        $list = array_slice($list, 0, self::MAX_INSTALLMENTS);

        if ($list === []) {
            unset($schedule['installments']);

            return $schedule;
        }

        $total = round(max(0, $totalAmount), 2);
        $normalizedRows = [];

        foreach ($list as $row) {
            $normalizedRows[] = self::normalizeRow($row);
        }

        if ($total <= 0) {
            $schedule['installments'] = $normalizedRows;

            return self::stripLegacyKeys($schedule);
        }

        $count = count($normalizedRows);
        if ($count === 1) {
            $normalizedRows[0]['percent'] = 100.0;
            $normalizedRows[0]['amount'] = $total;

            return self::stripLegacyKeys(array_merge($schedule, ['installments' => $normalizedRows]));
        }

        $allocated = 0.0;
        $percentSum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $isLast = $i === $count - 1;

            if ($isLast) {
                $normalizedRows[$i]['amount'] = round($total - $allocated, 2);
                $normalizedRows[$i]['percent'] = round(max(0, 100.0 - $percentSum), 2);

                break;
            }

            $percent = self::clampPercent((float) ($normalizedRows[$i]['percent'] ?? 0));
            $amount = round($total * ($percent / 100.0), 2);
            $normalizedRows[$i]['percent'] = $percent;
            $normalizedRows[$i]['amount'] = $amount;
            $allocated += $amount;
            $percentSum += $percent;
        }

        return self::stripLegacyKeys(array_merge($schedule, ['installments' => $normalizedRows]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row): array
    {
        $offset = (int) ($row['offset_days'] ?? 0);
        $offset = max(-730, min(730, $offset));

        $unitRaw = strtolower((string) ($row['offset_unit'] ?? CalendarBankDayShifter::UNIT_CALENDAR));
        $unit = $unitRaw === CalendarBankDayShifter::UNIT_BANK
            ? CalendarBankDayShifter::UNIT_BANK
            : CalendarBankDayShifter::UNIT_CALENDAR;

        $anchorRaw = strtolower(trim((string) ($row['anchor'] ?? 'first_loading')));
        $anchor = in_array($anchorRaw, ['first_loading', 'last_unloading', 'border_crossing', 'order_date', 'loading_date', 'unloading_date'], true)
            ? $anchorRaw
            : 'first_loading';

        $basisRaw = strtolower(trim((string) ($row['basis'] ?? 'fttn')));
        $basis = in_array($basisRaw, ['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'], true)
            ? $basisRaw
            : 'fttn';

        return [
            'percent' => self::clampPercent((float) ($row['percent'] ?? 0)),
            'amount' => isset($row['amount']) ? round((float) $row['amount'], 2) : null,
            'offset_days' => $offset,
            'offset_unit' => $unit,
            'anchor' => $anchor,
            'basis' => $basis,
        ];
    }

    private static function clampPercent(float $p): float
    {
        return max(0.0, min(100.0, round($p, 2)));
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    private static function stripLegacyKeys(array $schedule): array
    {
        foreach (['has_prepayment', 'prepayment_ratio', 'prepayment_days', 'prepayment_mode', 'postpayment_days', 'postpayment_mode'] as $legacy) {
            unset($schedule[$legacy]);
        }

        return $schedule;
    }
}
