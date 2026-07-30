<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\RoutePoint;
use Illuminate\Support\Facades\Schema;

class OrderCustomerPortalPresentationService
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
    ) {}

    /**
     * @return array{code: string, label: string}
     */
    public function tripStatus(Order $order): array
    {
        $code = (string) ($order->status ?: 'new');

        return [
            'code' => $code,
            'label' => $this->orderStatusService->label($code),
        ];
    }

    /**
     * @return list<array{title: string, address: string|null, planned_date: string|null, actual_date: string|null}>
     */
    public function routeMilestones(Order $order): array
    {
        if (! Schema::hasTable('order_legs') || ! Schema::hasTable('route_points')) {
            return [];
        }

        $order->loadMissing([
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $milestones = [];

        foreach ($order->legs as $leg) {
            foreach ($leg->routePoints as $point) {
                $milestones[] = $this->serializePoint($point);
            }
        }

        return $milestones;
    }

    /**
     * @return array{title: string, address: string|null, planned_date: string|null, actual_date: string|null}
     */
    private function serializePoint(RoutePoint $point): array
    {
        $title = match ((string) $point->type) {
            'loading' => 'Погрузка',
            'unloading' => 'Выгрузка',
            'border_crossing' => 'Граница',
            default => 'Точка маршрута',
        };

        return [
            'title' => $title,
            'address' => $point->address ?: data_get($point->normalized_data, 'address'),
            'planned_date' => optional($point->planned_date)?->toDateString(),
            'actual_date' => optional($point->actual_date)?->toDateString(),
        ];
    }
}
