<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\VatRate;
use Illuminate\Support\Facades\Schema;

/**
 * Категории формы оплаты для расчёта маржи (в т.ч. дополнение при 0% НДС у заказчика).
 */
final class PaymentFormVat
{
    private const LEGACY_VAT = 'vat';

    /** @var array<string, float>|null */
    private static ?array $ratePercentByCode = null;

    public static function isVatCode(?string $code): bool
    {
        if ($code === null || trim($code) === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($code), 'UTF-8');

        if ($normalized === 'no_vat' || $normalized === 'cash' || $normalized === 'mixed') {
            return false;
        }

        if ($normalized === self::LEGACY_VAT || str_starts_with($normalized, 'vat_')) {
            return true;
        }

        return self::ratePercentForCode($code) !== null;
    }

    public static function isNoVatCode(?string $code): bool
    {
        if ($code === null || trim($code) === '') {
            return false;
        }

        return mb_strtolower(trim($code), 'UTF-8') === 'no_vat';
    }

    public static function ratePercentForCode(?string $code): ?float
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($code), 'UTF-8');

        if ($normalized === self::LEGACY_VAT) {
            $rates = self::ratePercentMap();

            return $rates[self::LEGACY_VAT] ?? ($rates === [] ? null : reset($rates));
        }

        if ($normalized === 'no_vat' || $normalized === 'cash' || $normalized === 'mixed') {
            return null;
        }

        if (preg_match('/^vat_(\d+(?:\.\d+)?)$/', $normalized, $matches) === 1) {
            return (float) $matches[1];
        }

        $rates = self::ratePercentMap();

        if (array_key_exists($normalized, $rates)) {
            return (float) $rates[$normalized];
        }

        return null;
    }

    /**
     * Кривая сделка: с одной стороны любой НДС, с другой «без НДС».
     * Разные ставки НДС (5% и 22%) — прямая сделка.
     *
     * @param  list<string>  $carrierPaymentForms
     */
    public static function isIndirectDeal(?string $customerPaymentForm, array $carrierPaymentForms): bool
    {
        $customer = trim((string) $customerPaymentForm);

        if ($customer === '' || $carrierPaymentForms === []) {
            return false;
        }

        $customerIsVat = self::isVatCode($customer);
        $customerIsNoVat = self::isNoVatCode($customer);

        foreach ($carrierPaymentForms as $carrierForm) {
            $carrier = trim((string) $carrierForm);

            if ($carrier === '') {
                continue;
            }

            if ($customerIsVat && self::isNoVatCode($carrier)) {
                return true;
            }

            if ($customerIsNoVat && self::isVatCode($carrier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, float>
     */
    private static function ratePercentMap(): array
    {
        if (self::$ratePercentByCode !== null) {
            return self::$ratePercentByCode;
        }

        if (! Schema::hasTable('vat_rates')) {
            self::$ratePercentByCode = [];

            return self::$ratePercentByCode;
        }

        self::$ratePercentByCode = VatRate::query()
            ->get(['code', 'rate_percent'])
            ->mapWithKeys(fn (VatRate $row): array => [
                mb_strtolower((string) $row->code, 'UTF-8') => (float) $row->rate_percent,
            ])
            ->all();

        return self::$ratePercentByCode;
    }
}
