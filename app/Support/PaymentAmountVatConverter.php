<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\VatRate;
use Illuminate\Support\Facades\Schema;

/**
 * Представление суммы «с НДС» / «без НДС» для подсказок в считалке.
 * В формуле дельты заказа суммы хранятся как введены; здесь — только отображение вариантов.
 */
final class PaymentAmountVatConverter
{
    /**
     * @return array{without_vat: float, with_vat: float, vat_rate_percent: float, vat_label: string|null}
     */
    public static function dualPresentation(float $amount, ?string $paymentForm): array
    {
        $formRate = self::ratePercentForCode($paymentForm);
        $defaultRate = self::defaultVatRatePercent();
        $defaultLabel = self::defaultVatLabel();

        $net = self::normalizeToNet($amount, $paymentForm);
        $grossAtForm = $formRate > 0
            ? round($net * (1 + $formRate / 100), 2)
            : round($net, 2);
        $grossAtDefault = $defaultRate > 0
            ? round($net * (1 + $defaultRate / 100), 2)
            : round($net, 2);

        return [
            'without_vat' => round($net, 2),
            'with_vat' => $formRate > 0 ? $grossAtForm : $grossAtDefault,
            'vat_rate_percent' => $formRate > 0 ? $formRate : $defaultRate,
            'vat_label' => $formRate > 0
                ? PaymentFormDictionary::labelForCode($paymentForm)
                : $defaultLabel,
        ];
    }

    public static function ratePercentForCode(?string $code): float
    {
        if ($code === null || trim($code) === '') {
            return 0.0;
        }

        $lower = mb_strtolower(trim($code), 'UTF-8');

        if (in_array($lower, ['no_vat', 'cash', 'mixed'], true)) {
            return 0.0;
        }

        if ($lower === 'vat') {
            return self::defaultVatRatePercent();
        }

        if (! Schema::hasTable('vat_rates')) {
            return 0.0;
        }

        $row = VatRate::query()->where('code', $code)->first(['rate_percent']);

        return $row !== null ? (float) $row->rate_percent : 0.0;
    }

    public static function normalizeToNet(float $amount, ?string $paymentForm): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $rate = self::ratePercentForCode($paymentForm);

        if ($rate <= 0 || ! self::amountIsGrossByDefault($paymentForm)) {
            return $amount;
        }

        return $amount / (1 + ($rate / 100));
    }

    public static function amountIsGrossByDefault(?string $paymentForm): bool
    {
        if ($paymentForm === null || trim($paymentForm) === '') {
            return false;
        }

        $lower = mb_strtolower(trim($paymentForm), 'UTF-8');

        return $lower === 'vat' || str_starts_with($lower, 'vat_');
    }

    public static function defaultVatRatePercent(): float
    {
        if (! Schema::hasTable('vat_rates')) {
            return 20.0;
        }

        $row = VatRate::query()
            ->orderBy('sort_order')
            ->orderByDesc('rate_percent')
            ->first(['rate_percent']);

        return $row !== null ? (float) $row->rate_percent : 20.0;
    }

    public static function defaultVatLabel(): ?string
    {
        if (! Schema::hasTable('vat_rates')) {
            return 'С НДС';
        }

        $row = VatRate::query()
            ->orderBy('sort_order')
            ->orderByDesc('rate_percent')
            ->first(['label']);

        return $row !== null ? (string) $row->label : 'С НДС';
    }

    public static function presentationRatePercent(?string $paymentForm): float
    {
        $formRate = self::ratePercentForCode($paymentForm);

        return $formRate > 0 ? $formRate : self::defaultVatRatePercent();
    }

    /**
     * @return array{without_vat: float|null, with_vat: float|null, vat_rate_percent: float}
     */
    public static function pairFromNet(?float $net, ?string $paymentForm): array
    {
        if ($net === null) {
            return [
                'without_vat' => null,
                'with_vat' => null,
                'vat_rate_percent' => self::presentationRatePercent($paymentForm),
            ];
        }

        $normalized = max(0.0, $net);
        $rate = self::presentationRatePercent($paymentForm);

        return [
            'without_vat' => round($normalized, 2),
            'with_vat' => round($normalized * (1 + ($rate / 100)), 2),
            'vat_rate_percent' => $rate,
        ];
    }

    public static function netFromGrossAmount(float $gross, ?string $paymentForm): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        $rate = self::presentationRatePercent($paymentForm);

        if ($rate <= 0) {
            return $gross;
        }

        return round($gross / (1 + ($rate / 100)), 2);
    }
}
