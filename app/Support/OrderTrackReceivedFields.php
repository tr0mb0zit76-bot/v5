<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Даты получения оригиналов: отдельно заявка и закрывающие по стороне.
 */
final class OrderTrackReceivedFields
{
    public const CUSTOMER_REQUEST = 'track_received_date_customer_request';

    public const CUSTOMER_CLOSING = 'track_received_date_customer_closing';

    public const CARRIER_REQUEST = 'track_received_date_carrier_request';

    public const CARRIER_CLOSING = 'track_received_date_carrier_closing';

    /** @deprecated Одна дата на сторону — fallback / синхронизация в гриде. */
    public const CUSTOMER_LEGACY = 'track_received_date_customer';

    /** @deprecated Одна дата на сторону — fallback / синхронизация в гриде. */
    public const CARRIER_LEGACY = 'track_received_date_carrier';

    /**
     * @return list<string>
     */
    public static function specificFields(): array
    {
        return [
            self::CUSTOMER_REQUEST,
            self::CUSTOMER_CLOSING,
            self::CARRIER_REQUEST,
            self::CARRIER_CLOSING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allEditableFields(): array
    {
        return [
            ...self::specificFields(),
            self::CUSTOMER_LEGACY,
            self::CARRIER_LEGACY,
        ];
    }

    public static function isEditableField(string $field): bool
    {
        return in_array($field, self::allEditableFields(), true);
    }

    public static function fieldForSlotKind(string $party, string $slotKind): ?string
    {
        $party = trim($party);
        $slotKind = trim($slotKind);

        if ($party === 'customer') {
            return match ($slotKind) {
                'customer_request' => self::CUSTOMER_REQUEST,
                'customer_closing' => self::CUSTOMER_CLOSING,
                default => null,
            };
        }

        if ($party === 'carrier') {
            return match ($slotKind) {
                'carrier_request' => self::CARRIER_REQUEST,
                'carrier_closing' => self::CARRIER_CLOSING,
                default => null,
            };
        }

        return null;
    }

    public static function partyForField(string $field): ?string
    {
        return match ($field) {
            self::CUSTOMER_REQUEST, self::CUSTOMER_CLOSING, self::CUSTOMER_LEGACY => 'customer',
            self::CARRIER_REQUEST, self::CARRIER_CLOSING, self::CARRIER_LEGACY => 'carrier',
            default => null,
        };
    }

    public static function packageKindForField(string $field): ?string
    {
        return match ($field) {
            self::CUSTOMER_REQUEST, self::CARRIER_REQUEST => 'request',
            self::CUSTOMER_CLOSING, self::CARRIER_CLOSING => 'closing',
            default => null,
        };
    }

    /**
     * Для ottn — дата оригиналов заявки; для fttn_receipt — закрывающих.
     */
    public static function resolveForPaymentBasis(Order $order, string $party, string $basis): ?CarbonInterface
    {
        $basis = strtolower(trim($basis));
        $party = trim($party);

        if ($basis === 'ottn') {
            return self::resolvePackageDate($order, $party, 'request');
        }

        if ($basis === 'fttn_receipt') {
            return self::resolvePackageDate($order, $party, 'closing');
        }

        return self::resolveLegacyPartyDate($order, $party);
    }

    public static function resolvePackageDate(Order $order, string $party, string $packageKind): ?CarbonInterface
    {
        $field = match (true) {
            $party === 'customer' && $packageKind === 'request' => self::CUSTOMER_REQUEST,
            $party === 'customer' && $packageKind === 'closing' => self::CUSTOMER_CLOSING,
            $party === 'carrier' && $packageKind === 'request' => self::CARRIER_REQUEST,
            $party === 'carrier' && $packageKind === 'closing' => self::CARRIER_CLOSING,
            default => null,
        };

        if ($field !== null && $order->{$field} !== null) {
            return $order->{$field};
        }

        return self::resolveLegacyPartyDate($order, $party);
    }

    public static function resolveLegacyPartyDate(Order $order, string $party): ?CarbonInterface
    {
        if ($party === 'customer') {
            return $order->track_received_date_customer;
        }

        if ($party === 'carrier') {
            return $order->track_received_date_carrier;
        }

        return null;
    }

    /**
     * После записи конкретной даты синхронизируем легаси-поле стороны (max непустых).
     *
     * @return array<string, mixed>
     */
    public static function legacySyncAttributes(Order $order, string $writtenField): array
    {
        $party = self::partyForField($writtenField);

        if ($party === null) {
            return [];
        }

        if ($party === 'customer') {
            $latest = collect([
                $order->{self::CUSTOMER_REQUEST},
                $order->{self::CUSTOMER_CLOSING},
            ])
                ->filter(fn (mixed $date): bool => $date !== null)
                ->sortBy(fn (mixed $date): int => ($date instanceof CarbonInterface
                    ? $date->getTimestamp()
                    : strtotime((string) $date)) ?: 0)
                ->last();

            return [self::CUSTOMER_LEGACY => $latest];
        }

        $latest = collect([
            $order->{self::CARRIER_REQUEST},
            $order->{self::CARRIER_CLOSING},
        ])
            ->filter(fn (mixed $date): bool => $date !== null)
            ->sortBy(fn (mixed $date): int => ($date instanceof CarbonInterface
                ? $date->getTimestamp()
                : strtotime((string) $date)) ?: 0)
            ->last();

        return [self::CARRIER_LEGACY => $latest];
    }
}
