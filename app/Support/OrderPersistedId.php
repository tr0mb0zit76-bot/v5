<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use InvalidArgumentException;

/**
 * Надёжное получение ID сохранённого заказа (после create/update модель иногда теряет ключ).
 */
final class OrderPersistedId
{
    public static function resolveOrFail(Order $order): int
    {
        $orderId = self::resolve($order);

        if ($orderId === null) {
            throw new InvalidArgumentException('Заказ должен быть сохранён в БД до синхронизации связанных данных.');
        }

        return $orderId;
    }

    public static function resolve(Order $order): ?int
    {
        $orderId = (int) $order->getKey();

        if ($orderId > 0) {
            return $orderId;
        }

        if ($order->exists) {
            $order->refresh();
            $orderId = (int) $order->getKey();
        }

        return $orderId > 0 ? $orderId : null;
    }
}
