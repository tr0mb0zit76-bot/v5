<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\OrderClosingDocumentsNotificationService;
use App\Services\OrderCompensationService;
use App\Services\OrderStatusService;
use App\Support\OrderAgentLexicon;
use App\Support\OrderRouteMilestoneDateResolver;
use App\Support\PerformerRouteActualDates;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderRouteActualDateUpdateService
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderCompensationService $orderCompensationService,
        private readonly OrderClosingDocumentsNotificationService $closingDocumentsNotificationService,
    ) {}

    /**
     * @return array{kind: string, kind_label: string, date: string, order_id: int}
     */
    public function apply(User $user, Order $order, string $kind, mixed $date, ?string $legStage = null): array
    {
        $kind = OrderAgentLexicon::resolveRouteActualKind($kind) ?? $kind;

        if (! in_array($kind, ['loading_actual', 'unloading_actual'], true)) {
            throw ValidationException::withMessages([
                'kind' => 'Укажите loading_actual (фактическая погрузка) или unloading_actual (фактическая выгрузка).',
            ]);
        }

        $normalizedDate = OrderAgentLexicon::normalizeDateValue($date);

        if ($normalizedDate === null) {
            throw ValidationException::withMessages([
                'date' => 'Укажите дату в формате Y-m-d или dd.mm.yyyy.',
            ]);
        }

        $stage = PerformerRouteActualDates::normalizeStageKey($legStage ?? 'leg_1');
        $routeType = $kind === 'loading_actual' ? 'loading' : 'unloading';

        $order->loadMissing([
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $routePoint = $this->resolveRoutePoint($order, $stage, $routeType);

        if ($routePoint === null) {
            throw ValidationException::withMessages([
                'kind' => 'Точка маршрута «'.$routeType.'» для плеча '.$stage.' не найдена.',
            ]);
        }

        $routePoint->forceFill(['actual_date' => $normalizedDate])->save();
        $this->syncPerformersJson($order, $stage, $kind, $normalizedDate);

        $previousStatus = (string) $order->status;
        $order->refresh()->loadMissing([
            'legs.routePoints',
            'documents',
        ]);

        $derivedStatus = $this->orderStatusService->resolve($order, $order->manual_status);

        $order->forceFill([
            'status' => $derivedStatus,
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'updated_by' => $user->id,
            'is_active' => ! in_array($derivedStatus, ['closed', 'cancelled', 'disruption'], true),
        ])->save();

        if ($previousStatus !== $derivedStatus && Schema::hasTable('order_status_logs')) {
            OrderStatusLog::query()->create([
                'order_id' => $order->id,
                'status_from' => $previousStatus,
                'status_to' => $derivedStatus,
                'comment' => null,
                'created_by' => $user->id,
            ]);
        }

        $orderForSchedule = OrderRouteMilestoneDateResolver::syncToOrder($order->fresh() ?? $order);
        $this->orderCompensationService->resyncPaymentSchedulesForOrder($orderForSchedule);

        if ($kind === 'unloading_actual') {
            $this->closingDocumentsNotificationService->maybeNotify($orderForSchedule->fresh([
                'legs.routePoints',
                'documents',
                'client',
            ]) ?? $orderForSchedule);
        }

        $kindLabel = OrderAgentLexicon::labelFor($kind) ?? $kind;

        return [
            'order_id' => $order->id,
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'date' => $normalizedDate,
            'leg_stage' => $stage,
            'status' => $derivedStatus,
        ];
    }

    private function resolveRoutePoint(Order $order, string $stage, string $routeType): ?RoutePoint
    {
        $targetLeg = $order->legs->first(
            fn ($leg): bool => PerformerRouteActualDates::stagesMatch((string) ($leg->description ?? 'leg_1'), $stage),
        ) ?? $order->legs->sortBy('sequence')->first();

        if ($targetLeg === null) {
            return null;
        }

        $points = $targetLeg->routePoints->sortBy('sequence')->values();

        if ($routeType === 'loading') {
            return $points->first(fn (RoutePoint $point): bool => $point->type === 'loading');
        }

        return $points->filter(fn (RoutePoint $point): bool => $point->type === 'unloading')->last();
    }

    private function syncPerformersJson(Order $order, string $stage, string $kind, string $date): void
    {
        if (! Schema::hasColumn('orders', 'performers')) {
            return;
        }

        $performers = is_array($order->performers) ? $order->performers : [];

        if ($performers === []) {
            $performers = [[
                'stage' => $stage,
                'carrier_mode' => 'single',
                'loading_actual' => $kind === 'loading_actual' ? $date : null,
                'unloading_actual' => $kind === 'unloading_actual' ? $date : null,
            ]];
            $order->forceFill(['performers' => $performers])->save();

            return;
        }

        $updated = false;

        foreach ($performers as $index => $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (! PerformerRouteActualDates::stagesMatch((string) ($performer['stage'] ?? 'leg_1'), $stage)) {
                continue;
            }

            if (PerformerRouteActualDates::isSplitPerformer($performer)) {
                $slots = is_array($performer['split_carriers'] ?? null) ? $performer['split_carriers'] : [];
                if ($slots !== [] && is_array($slots[0])) {
                    $slots[0][$kind] = $date;
                    $performers[$index]['split_carriers'] = $slots;
                    $updated = true;
                }
            } else {
                $performers[$index][$kind] = $date;
                $updated = true;
            }

            break;
        }

        if (! $updated) {
            $performers[] = [
                'stage' => $stage,
                'carrier_mode' => 'single',
                'loading_actual' => $kind === 'loading_actual' ? $date : null,
                'unloading_actual' => $kind === 'unloading_actual' ? $date : null,
            ];
        }

        $order->forceFill(['performers' => $performers])->save();
    }
}
