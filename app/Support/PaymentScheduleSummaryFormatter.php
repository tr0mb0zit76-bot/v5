<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Текстовая сводка графика оплаты для заказа, печати и поля {@see FinancialTerm::$client_payment_terms}.
 */
final class PaymentScheduleSummaryFormatter
{
    /** @var array<string, string> */
    private const MODE_LABELS = [
        'fttn' => 'по сканам',
        'fttn_receipt' => 'по сканам + квиток',
        'ottn' => 'по оригиналам',
        'loading' => 'при погрузке',
        'unloading' => 'при выгрузке',
    ];

    /** @var array<string, string> */
    private const ANCHOR_LABELS = [
        'first_loading' => 'первой погрузки',
        'last_unloading' => 'последней выгрузки',
        'border_crossing' => 'прохождения границы',
        'order_date' => 'даты заказа',
        'loading_date' => 'даты погрузки (заказ)',
        'unloading_date' => 'даты выгрузки (заказ)',
    ];

    /**
     * Подмена устаревших подписей в уже сохранённых строках условий оплаты (до обновления {@see self::MODE_LABELS}).
     */
    public static function humanizeStoredSummary(?string $summary): ?string
    {
        if ($summary === null) {
            return null;
        }

        $trimmed = trim($summary);
        if ($trimmed === '') {
            return null;
        }

        $replacements = [
            'ФТТН + квиток' => self::MODE_LABELS['fttn_receipt'],
            'ФТТН' => self::MODE_LABELS['fttn'],
            'ОТТН' => self::MODE_LABELS['ottn'],
            'На загрузке' => self::MODE_LABELS['loading'],
            'На выгрузке' => self::MODE_LABELS['unloading'],
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $trimmed);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, ?string>  $dateContext
     */
    public static function format(
        array $schedule,
        float $totalAmount = 0.0,
        string $currency = 'RUB',
        ?Order $order = null,
        array $dateContext = [],
    ): string {
        $schedule = PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($schedule);
        $normalized = PaymentInstallmentScheduleNormalizer::normalize($schedule, $totalAmount);

        return self::formatInstallments($normalized, $totalAmount, $currency, $order, $dateContext);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private static function formatLegacyHuman(array $schedule): string
    {
        $normalized = self::normalizeLegacy($schedule);
        $postPercent = $normalized['has_prepayment']
            ? max(0, 100 - $normalized['prepayment_ratio'])
            : 100;
        $postBasis = self::basisLabel($normalized['postpayment_mode']);
        $postPart = self::formatPercentSummary((float) $postPercent)
            .'% в течение '.$normalized['postpayment_days'].' кал. дн. '.$postBasis;

        if (! $normalized['has_prepayment']) {
            return $postPart;
        }

        $preBasis = self::basisLabel($normalized['prepayment_mode']);
        $prePart = self::formatPercentSummary((float) $normalized['prepayment_ratio'])
            .'% в течение '.$normalized['prepayment_days'].' кал. дн. '.$preBasis;

        return $prePart.'; '.$postPart;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, ?string>  $dateContext
     */
    private static function formatInstallments(
        array $schedule,
        float $totalAmount,
        string $currency,
        ?Order $order,
        array $dateContext,
    ): string {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values(array_filter($schedule['installments'] ?? [], static fn ($r): bool => is_array($r)));
        if ($rows === []) {
            return '';
        }

        $ctx = $dateContext !== [] ? $dateContext : ($order !== null ? PaymentInstallmentPlanner::dateContextFromOrder($order) : []);
        $lastUnloading = $ctx['last_unloading'] ?? null;

        $parts = [];
        foreach ($rows as $row) {
            $pct = (float) ($row['percent'] ?? 0);
            $amt = (float) ($row['amount'] ?? 0);
            $offset = (int) ($row['offset_days'] ?? 0);
            $unit = (string) ($row['offset_unit'] ?? CalendarBankDayShifter::UNIT_CALENDAR);
            $anchorKey = (string) ($row['anchor'] ?? 'first_loading');
            $anchorHuman = self::ANCHOR_LABELS[$anchorKey] ?? $anchorKey;

            $offsetPhrase = self::offsetPhrase($offset, $unit, $anchorHuman);
            $planned = PaymentInstallmentPlanner::plannedDateForInstallment($row, $order, $ctx);
            $omitBasis = self::installmentShouldOmitBasis($row, $planned, $lastUnloading);

            $money = $totalAmount > 0
                ? self::formatMoneyRu($amt, $currency)
                : '';

            $pctStr = self::formatPercentSummary($pct);

            $basis = self::basisLabel((string) ($row['basis'] ?? 'fttn'));

            if ($money !== '') {
                $piece = "{$pctStr}% ({$money}), {$offsetPhrase}";
            } else {
                $piece = "{$pctStr}%, {$offsetPhrase}";
            }

            if (! $omitBasis) {
                $piece .= ', '.$basis;
            }

            $parts[] = $piece;
        }

        return implode('; ', $parts);
    }

    private static function formatPercentSummary(float $pct): string
    {
        $s = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function installmentShouldOmitBasis(array $row, ?string $plannedIso, ?string $lastUnloadingIso): bool
    {
        if ((int) ($row['offset_days'] ?? 0) < 0) {
            return true;
        }

        if ($plannedIso !== null && $lastUnloadingIso !== null) {
            try {
                if (Carbon::parse($plannedIso)->lt(Carbon::parse($lastUnloadingIso))) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    private static function formatMoneyRu(float $amount, string $currency): string
    {
        return number_format($amount, 2, ',', ' ').' '.$currency;
    }

    private static function offsetPhrase(int $offsetDays, string $unit, string $anchorHuman): string
    {
        $abs = abs($offsetDays);
        $unitShort = $unit === CalendarBankDayShifter::UNIT_BANK ? 'банк.' : 'календ.';
        $unitWord = 'дн';

        if ($offsetDays === 0) {
            return "в день якоря ({$anchorHuman})";
        }

        if ($offsetDays < 0) {
            return "за {$abs} {$unitShort} {$unitWord} до {$anchorHuman}";
        }

        return "через {$abs} {$unitShort} {$unitWord} после {$anchorHuman}";
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array{has_prepayment: bool, prepayment_ratio: int, prepayment_days: int, prepayment_mode: string, postpayment_days: int, postpayment_mode: string}
     */
    private static function normalizeLegacy(array $schedule): array
    {
        $raw = $schedule['has_prepayment'] ?? false;
        $hasPrepayment = $raw === true || $raw === 1 || $raw === '1';

        return [
            'has_prepayment' => $hasPrepayment,
            'prepayment_ratio' => (int) ($schedule['prepayment_ratio'] ?? 50),
            'prepayment_days' => (int) ($schedule['prepayment_days'] ?? 0),
            'prepayment_mode' => (string) ($schedule['prepayment_mode'] ?? 'fttn'),
            'postpayment_days' => (int) ($schedule['postpayment_days'] ?? 0),
            'postpayment_mode' => (string) ($schedule['postpayment_mode'] ?? 'ottn'),
        ];
    }

    private static function basisLabel(string $mode): string
    {
        $key = strtolower($mode);

        return self::MODE_LABELS[$key] ?? $mode;
    }
}
