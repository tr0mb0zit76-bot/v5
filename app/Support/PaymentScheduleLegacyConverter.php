<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Конвертация классического графика (has_prepayment / postpayment_*) в единый формат installments.
 */
final class PaymentScheduleLegacyConverter
{
    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public static function toInstallments(array $schedule): array
    {
        if (PaymentInstallmentScheduleNormalizer::isInstallmentModel($schedule)) {
            return $schedule;
        }

        $raw = $schedule['has_prepayment'] ?? false;
        $hasPrepayment = $raw === true || $raw === 1 || $raw === '1';

        /** @var list<array<string, mixed>> $installments */
        $installments = [];

        if ($hasPrepayment) {
            $preRatio = max(0, min(100, (float) ($schedule['prepayment_ratio'] ?? 0)));
            if ($preRatio > 0) {
                $installments[] = self::legacyRow(
                    $preRatio,
                    (int) ($schedule['prepayment_days'] ?? 0),
                    (string) ($schedule['prepayment_mode'] ?? 'fttn'),
                );
            }
        }

        $postPct = $hasPrepayment
            ? max(0, 100 - (float) ($schedule['prepayment_ratio'] ?? 0))
            : 100.0;

        if ($postPct > 0) {
            $installments[] = self::legacyRow(
                $postPct,
                (int) ($schedule['postpayment_days'] ?? 0),
                (string) ($schedule['postpayment_mode'] ?? 'ottn'),
            );
        }

        if ($installments === []) {
            $installments[] = self::legacyRow(100, 0, 'ottn');
        }

        return ['installments' => $installments];
    }

    /**
     * @return array{percent: float, amount: null, offset_days: int, offset_unit: string, anchor: string, basis: string}
     */
    private static function legacyRow(float $percent, int $days, string $mode): array
    {
        $basis = strtolower(trim($mode));
        if (! in_array($basis, ['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'], true)) {
            $basis = 'ottn';
        }

        return [
            'percent' => round($percent, 2),
            'amount' => null,
            'offset_days' => max(0, $days),
            'offset_unit' => 'calendar_days',
            'anchor' => self::anchorForBasis($basis),
            'basis' => $basis,
        ];
    }

    public static function anchorForBasis(string $basis): string
    {
        return match (strtolower(trim($basis))) {
            'loading' => 'first_loading',
            'unloading' => 'last_unloading',
            default => 'last_unloading',
        };
    }
}
