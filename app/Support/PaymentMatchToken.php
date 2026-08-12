<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\PaymentSchedule;
use InvalidArgumentException;

/**
 * Токен однозначного матчинга платежа ↔ строка графика.
 *
 * Формат: CRM:{orderNumber}:{C|P}{seq}
 * Пример: CRM:АС-2608-0042:C1  (заказчик, транш 1)
 *         CRM:АС-2608-0042:P2  (перевозчик, транш 2)
 */
final class PaymentMatchToken
{
    public const PREFIX = 'CRM';

    public const SIDE_CUSTOMER = 'C';

    public const SIDE_CARRIER = 'P';

    /**
     * @return array{order_number: string, side: string, sequence: ?int, token: string}|null
     */
    public static function parse(string $text): ?array
    {
        if (preg_match('/\bCRM:([^\s:]+):(C|P)(\d*)\b/ui', $text, $matches) !== 1) {
            return null;
        }

        $sequence = $matches[3] !== '' ? (int) $matches[3] : null;
        if ($sequence !== null && $sequence < 1) {
            return null;
        }

        $side = strtoupper($matches[2]);
        $orderNumber = $matches[1];
        $token = self::PREFIX.':'.$orderNumber.':'.$side.($sequence ?? '');

        return [
            'order_number' => $orderNumber,
            'side' => $side,
            'sequence' => $sequence,
            'token' => $token,
        ];
    }

    public static function containsToken(string $text): bool
    {
        return self::parse($text) !== null;
    }

    public static function forSchedule(PaymentSchedule $schedule): string
    {
        $schedule->loadMissing('order:id,order_number');
        $orderNumber = self::resolveOrderNumber($schedule->order);
        $side = self::sideFromParty((string) ($schedule->party ?? ''));
        $sequence = max(1, (int) ($schedule->installment_sequence ?? 1));

        return self::PREFIX.':'.$orderNumber.':'.$side.$sequence;
    }

    public static function forOrderCustomer(Order $order, int $sequence = 1): string
    {
        $orderNumber = self::resolveOrderNumber($order);
        $sequence = max(1, $sequence);

        return self::PREFIX.':'.$orderNumber.':'.self::SIDE_CUSTOMER.$sequence;
    }

    public static function purposeLine(PaymentSchedule $schedule): string
    {
        $schedule->loadMissing('order:id,order_number');
        $token = self::forSchedule($schedule);
        $orderNumber = self::resolveOrderNumber($schedule->order);

        return 'Оплата по заказу '.$orderNumber.' '.$token;
    }

    /**
     * Сторона графика → литера токена.
     */
    public static function sideFromParty(string $party): string
    {
        return match ($party) {
            'customer' => self::SIDE_CUSTOMER,
            'carrier', 'contractor' => self::SIDE_CARRIER,
            default => self::SIDE_CARRIER,
        };
    }

    public static function partyFromSide(string $side): string
    {
        return strtoupper($side) === self::SIDE_CUSTOMER ? 'customer' : 'carrier';
    }

    /**
     * Стоп: исходящий банковский платёж без токена в назначении.
     *
     * @throws InvalidArgumentException
     */
    public static function assertOutgoingBankPurpose(PaymentSchedule $schedule, string $paymentMethod, ?string $purpose): void
    {
        if (! (bool) config('one_c.payment_token.enforce_outgoing_bank', true)) {
            return;
        }

        if ($paymentMethod !== 'bank_transfer') {
            return;
        }

        $party = (string) ($schedule->party ?? '');
        if (! in_array($party, ['carrier', 'contractor'], true)) {
            return;
        }

        $purpose = trim((string) $purpose);
        if ($purpose !== '' && self::containsToken($purpose)) {
            $parsed = self::parse($purpose);
            $expected = self::forSchedule($schedule);
            if ($parsed !== null && strcasecmp($parsed['token'], $expected) === 0) {
                return;
            }
            // Токен есть, но на другую строку — всё равно стоп.
            throw new InvalidArgumentException(
                'В назначении должен быть токен этой строки графика: '.$expected
            );
        }

        throw new InvalidArgumentException(
            'Банковский платёж перевозчику без токена запрещён. Укажите в назначении: '
            .self::purposeLine($schedule)
        );
    }

    private static function resolveOrderNumber(?Order $order): string
    {
        if ($order === null) {
            return 'ID-0';
        }

        $number = trim((string) ($order->order_number ?? ''));

        return $number !== '' ? $number : 'ID-'.$order->id;
    }
}
