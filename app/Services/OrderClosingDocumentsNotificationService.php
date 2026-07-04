<?php

namespace App\Services;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\RoutePoint;
use App\Models\User;
use App\Notifications\CabinetInAppNotification;
use App\Services\Mobile\MobilePushService;
use App\Support\OrderClipboardSummaryResolver;
use App\Support\RoutePointActualMilestones;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderClosingDocumentsNotificationService
{
    private const METADATA_FLAG = 'closing_documents_notified_at';

    public function __construct(
        private readonly OrderDocumentRequirementService $documentRequirementService,
        private readonly MobilePushService $mobilePushService,
    ) {}

    public function maybeNotify(Order $order): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        $order->loadMissing(['legs.routePoints', 'documents', 'client']);

        if ($this->alreadyNotified($order) || ! $this->isTransportCompleted($order)) {
            return false;
        }

        $recipients = $this->resolveClerks();

        if ($recipients->isEmpty()) {
            return false;
        }

        $body = $this->buildNotificationBody($order);
        $actionUrl = route('orders.edit', [$order], absolute: false).'?tab=documents';
        $clipboardSummary = app(OrderClipboardSummaryResolver::class)
            ->mapForOrders(collect([$order->fresh(['client', 'legs.routePoints'])]))[(int) $order->id] ?? '';

        $notification = new CabinetInAppNotification(
            'order_closing_documents_required',
            'Закрывающие документы по перевозке',
            $body,
            $actionUrl,
            [
                'order_id' => $order->id,
                'clipboard_summary' => $clipboardSummary,
            ],
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
            $this->mobilePushService->notifyCabinetNotification($recipient, $notification);
        }

        $this->markNotified($order);

        return true;
    }

    public function isTransportCompleted(Order $order): bool
    {
        if (! $this->hasActualUnloadingDate($order)) {
            return false;
        }

        return $this->hasFulfilledWaybill($order);
    }

    public function buildNotificationBody(Order $order): string
    {
        $order->loadMissing(['client', 'legs.routePoints']);

        $customerName = trim((string) ($order->client?->name ?? ''));
        $customerName = $customerName !== '' ? $customerName : '—';

        $orderNumber = filled($order->order_number)
            ? (string) $order->order_number
            : '#'.$order->id;

        $orderDate = optional($order->order_date)?->format('d.m.Y') ?? '—';

        $loadingCity = $this->resolveRouteCity($order, 'loading') ?? '—';
        $unloadingCity = $this->resolveRouteCity($order, 'unloading', last: true) ?? '—';

        ['driver_name' => $driverName, 'tractor_plate' => $tractorPlate, 'trailer_plate' => $trailerPlate] = $this->resolveFleetDetails($order);

        $vehicleParts = array_values(array_filter([
            $tractorPlate !== null && $tractorPlate !== '' ? $tractorPlate : null,
            $trailerPlate !== null && $trailerPlate !== '' ? $trailerPlate : null,
        ]));
        $vehicleLabel = $vehicleParts !== [] ? implode(' ', $vehicleParts) : '—';

        return sprintf(
            'Необходимо подготовить закрывающие документы по перевозке с %s. Транспортно-экспедиционные услуги по заявке %s от %s, маршрут: %s - %s. Водитель %s, автомобиль %s.',
            $customerName,
            $orderNumber,
            $orderDate,
            $loadingCity,
            $unloadingCity,
            $driverName !== '' ? $driverName : '—',
            $vehicleLabel,
        );
    }

    private function alreadyNotified(Order $order): bool
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        return filled($metadata[self::METADATA_FLAG] ?? null);
    }

    private function markNotified(Order $order): void
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata[self::METADATA_FLAG] = now()->toIso8601String();

        $order->update(['metadata' => $metadata]);
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveClerks(): Collection
    {
        return User::query()
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn ($query) => $query->where('is_active', true),
            )
            ->whereHas('role', fn ($query) => $query->where('name', 'clerk'))
            ->get();
    }

    private function hasActualUnloadingDate(Order $order): bool
    {
        $milestones = RoutePointActualMilestones::forOrder($order);

        return $milestones['actual_unloading'] !== null;
    }

    private function hasFulfilledWaybill(Order $order): bool
    {
        $checklist = $this->documentRequirementService->checklistForOrder($order);

        foreach ($checklist as $item) {
            if (($item['key'] ?? '') === 'waybill' && ($item['completed'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function resolveRouteCity(Order $order, string $type, bool $last = false): ?string
    {
        $points = $order->legs
            ->sortBy('sequence')
            ->flatMap(fn ($leg) => $leg->routePoints->sortBy('sequence'))
            ->filter(fn (RoutePoint $point): bool => $point->type === $type)
            ->values();

        if ($points->isEmpty()) {
            return null;
        }

        /** @var RoutePoint $point */
        $point = $last ? $points->last() : $points->first();

        return $this->routePointCityLabel($point);
    }

    private function routePointCityLabel(RoutePoint $point): ?string
    {
        $candidates = [
            data_get($point->normalized_data, 'city'),
            data_get($point->metadata, 'normalized_data.city'),
            $point->address,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @return array{driver_name: string, tractor_plate: ?string, trailer_plate: ?string}
     */
    private function resolveFleetDetails(Order $order): array
    {
        $selection = $this->resolveFleetSelection($order);

        $driverName = '';
        if ($selection['fleet_driver_id'] !== null && Schema::hasTable('fleet_drivers')) {
            $driverName = trim((string) (FleetDriver::query()
                ->whereKey($selection['fleet_driver_id'])
                ->value('full_name') ?? ''));
        }

        $tractorPlate = null;
        $trailerPlate = null;

        if ($selection['fleet_vehicle_id'] !== null && Schema::hasTable('fleet_vehicles')) {
            $vehicle = FleetVehicle::query()
                ->whereKey($selection['fleet_vehicle_id'])
                ->first(['tractor_plate', 'trailer_plate']);

            if ($vehicle !== null) {
                $tractorPlate = $this->cleanPlate($vehicle->tractor_plate);
                $trailerPlate = $this->cleanPlate($vehicle->trailer_plate);
            }
        }

        return [
            'driver_name' => $driverName,
            'tractor_plate' => $tractorPlate,
            'trailer_plate' => $trailerPlate,
        ];
    }

    /**
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function resolveFleetSelection(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                ? (int) $performer['fleet_vehicle_id']
                : null;
            $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                ? (int) $performer['fleet_driver_id']
                : null;

            if ($vehicleId !== null || $driverId !== null) {
                return [
                    'fleet_vehicle_id' => $vehicleId,
                    'fleet_driver_id' => $driverId,
                ];
            }

            if (($performer['carrier_mode'] ?? '') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $vehicleId = isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null
                        ? (int) $slot['fleet_vehicle_id']
                        : null;
                    $driverId = isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null
                        ? (int) $slot['fleet_driver_id']
                        : null;

                    if ($vehicleId !== null || $driverId !== null) {
                        return [
                            'fleet_vehicle_id' => $vehicleId,
                            'fleet_driver_id' => $driverId,
                        ];
                    }
                }
            }
        }

        return [
            'fleet_vehicle_id' => null,
            'fleet_driver_id' => null,
        ];
    }

    private function cleanPlate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
