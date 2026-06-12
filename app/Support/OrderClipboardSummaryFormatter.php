<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class OrderClipboardSummaryFormatter
{
    private const PUBLIC_OFFER_CONTRACT_DATE = '29.05.2026 г.';

    public static function format(
        ?string $companyCode,
        ?string $customerName,
        ?string $orderNumber,
        mixed $orderDate,
        mixed $customerRate,
        ?string $customerPaymentForm,
        ?string $loadingCity,
        ?string $unloadingCity,
        ?string $tractorBrand,
        ?string $tractorPlate,
        ?string $trailerBrand,
        ?string $trailerPlate,
        ?string $driverName,
    ): string {
        $companyCodeLabel = self::display($companyCode);
        $customerLabel = self::display($customerName);
        $orderNumberLabel = self::display($orderNumber);
        $orderDateLabel = self::formatDate($orderDate);
        $costLabel = self::formatMoney($customerRate);
        $vatLabel = self::display(PaymentFormDictionary::labelForCode($customerPaymentForm));

        $header = sprintf(
            '%s %s заявка № %s от %s, %s, %s',
            $companyCodeLabel,
            $customerLabel,
            $orderNumberLabel,
            $orderDateLabel,
            $costLabel,
            $vatLabel,
        );

        $routeFrom = self::display($loadingCity);
        $routeTo = self::display($unloadingCity);
        $driverLabel = self::display($driverName);
        $vehicleLabel = self::vehicleSlashLabel(
            $tractorBrand,
            $tractorPlate,
            $trailerBrand,
            $trailerPlate,
        );

        $body = sprintf(
            'Транспортно-экспедиционные услуги по Заявке № %s от %s к Договору транспортной экспедиции (публичной оферте) от %s, маршрут %s - %s. Водитель %s, ТС %s.',
            $orderNumberLabel,
            $orderDateLabel,
            self::PUBLIC_OFFER_CONTRACT_DATE,
            $routeFrom,
            $routeTo,
            $driverLabel,
            $vehicleLabel,
        );

        return $header."\n\n".$body;
    }

    public static function vehicleSlashLabel(
        ?string $tractorBrand,
        ?string $tractorPlate,
        ?string $trailerBrand,
        ?string $trailerPlate,
    ): string {
        $parts = array_values(array_filter([
            self::clean($tractorBrand),
            self::clean($tractorPlate),
            self::clean($trailerBrand),
            self::clean($trailerPlate),
        ], fn (?string $value): bool => $value !== null));

        if ($parts === []) {
            return '—';
        }

        return implode(' / ', $parts);
    }

    private static function formatDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d.m.Y');
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return '—';
            }

            try {
                return Carbon::parse($trimmed)->format('d.m.Y');
            } catch (\Throwable) {
                return $trimmed;
            }
        }

        return '—';
    }

    private static function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 2, ',', ' ').' руб.';
    }

    private static function display(?string $value): string
    {
        return self::clean($value) ?? '—';
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
