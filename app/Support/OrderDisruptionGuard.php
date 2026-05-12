<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Validation\Validator;

final class OrderDisruptionGuard
{
    /**
     * Доп. проверки для статуса «Срыв» (transport never started, order already left «Новый»).
     *
     * @param  'status'|'value'  $errorAttribute
     */
    public static function validateMarkDisrupted(User $user, Order $order, Validator $validator, string $errorAttribute = 'status'): void
    {
        if (! $user->isAdmin() && ! $user->isSupervisor()) {
            $validator->errors()->add(
                $errorAttribute,
                'Статус «Срыв» может установить только руководитель или администратор.'
            );

            return;
        }

        $order->loadMissing([
            'legs' => fn ($q) => $q->orderBy('sequence'),
            'legs.routePoints' => fn ($q) => $q->orderBy('sequence'),
        ]);

        $effective = OrderDeleteAuthorization::effectiveWorkflowStatus(
            $order->getAttribute('manual_status'),
            $order->getAttribute('status')
        );

        if ($effective === 'new') {
            $validator->errors()->add(
                $errorAttribute,
                'Статус «Срыв» нельзя установить, пока заказ в статусе «Новый».'
            );

            return;
        }

        if (app(OrderStatusService::class)->hasFactOfLoadingOnRoute($order)) {
            $validator->errors()->add(
                $errorAttribute,
                'Статус «Срыв» можно установить только до фактической погрузки по маршруту.'
            );
        }
    }
}
