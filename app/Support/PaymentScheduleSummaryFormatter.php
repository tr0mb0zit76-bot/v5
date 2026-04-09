<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Текстовая сводка графика оплаты — в том же виде, что {@see resources/js/Pages/Orders/Wizard.vue} paymentScheduleSummary.
 */
final class PaymentScheduleSummaryFormatter
{
    /** @var array<string, string> */
    private const MODE_LABELS = [
        'fttn' => 'ФТТН',
        'ottn' => 'ОТТН',
        'loading' => 'На загрузке',
        'unloading' => 'На выгрузке',
    ];

    /**
     * @param  array<string, mixed>  $schedule
     */
    public static function format(array $schedule): string
    {
        $normalized = self::normalize($schedule);
        $postPercent = $normalized['has_prepayment']
            ? max(0, 100 - $normalized['prepayment_ratio'])
            : 100;
        $postLabel = self::basisLabel($normalized['postpayment_mode']);
        $postPart = "{$postPercent}% {$normalized['postpayment_days']} дн {$postLabel}";

        if (! $normalized['has_prepayment']) {
            return $postPart;
        }

        $preLabel = self::basisLabel($normalized['prepayment_mode']);

        return "{$normalized['prepayment_ratio']}% {$normalized['prepayment_days']} дн {$preLabel} / {$postPart}";
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array{has_prepayment: bool, prepayment_ratio: int, prepayment_days: int, prepayment_mode: string, postpayment_days: int, postpayment_mode: string}
     */
    private static function normalize(array $schedule): array
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
