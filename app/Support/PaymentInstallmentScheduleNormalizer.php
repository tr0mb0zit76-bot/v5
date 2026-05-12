<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Нормализация JSON графика с массивом {@see max 2} траншей: проценты, суммы, округление второй транши.
 */
final class PaymentInstallmentScheduleNormalizer
{
    public const MAX_INSTALLMENTS = 2;

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
    public static function normalize(array $schedule, float $totalAmount): array
    {
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

            return $schedule;
        }

        $count = count($normalizedRows);
        if ($count === 1) {
            $normalizedRows[0]['percent'] = 100.0;
            $normalizedRows[0]['amount'] = $total;

            return self::stripLegacyKeys(array_merge($schedule, ['installments' => $normalizedRows]));
        }

        $p1 = self::clampPercent((float) ($normalizedRows[0]['percent'] ?? 0));
        $p2 = max(0.0, min(100.0, round(100.0 - $p1, 2)));

        $a1 = round($total * ($p1 / 100.0), 2);
        $a2 = round($total - $a1, 2);

        $normalizedRows[0]['percent'] = $p1;
        $normalizedRows[0]['amount'] = $a1;
        $normalizedRows[1]['percent'] = $p2;
        $normalizedRows[1]['amount'] = $a2;

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
        $anchor = in_array($anchorRaw, ['first_loading', 'last_unloading', 'order_date', 'loading_date', 'unloading_date'], true)
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
