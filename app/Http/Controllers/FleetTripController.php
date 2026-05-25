<?php

namespace App\Http\Controllers;

use App\Models\FleetTrip;
use App\Models\FleetTripCostLine;
use App\Services\FleetTripService;
use App\Support\OwnFleetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FleetTripController extends Controller
{
    public function __construct(
        private readonly FleetTripService $fleetTripService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);

        $trips = FleetTrip::query()
            ->with(['order:id,order_number', 'vehicle:id,tractor_plate,trailer_plate', 'driver:id,full_name'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (FleetTrip $trip): array => $this->formatTripRow($trip));

        return Inertia::render('Fleet/Trips', [
            'trips' => $trips,
            'statusOptions' => $this->statusOptions(),
            'costCategoryOptions' => $this->costCategoryOptions(),
            'selectedTrip' => null,
        ]);
    }

    public function show(Request $request, FleetTrip $fleetTrip): Response
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);
        $fleetTrip->load(['order', 'vehicle.owner:id,name', 'driver', 'costLines']);

        $trips = FleetTrip::query()
            ->with(['order:id,order_number', 'vehicle:id,tractor_plate,trailer_plate', 'driver:id,full_name'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (FleetTrip $trip): array => $this->formatTripRow($trip));

        return Inertia::render('Fleet/Trips', [
            'trips' => $trips,
            'statusOptions' => $this->statusOptions(),
            'costCategoryOptions' => $this->costCategoryOptions(),
            'selectedTrip' => $this->formatTripDetail($fleetTrip),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);

        $validated = $this->validateTripPayload($request);
        $trip = FleetTrip::query()->create($validated);

        return to_route('fleet.trips.show', $trip);
    }

    public function update(Request $request, FleetTrip $fleetTrip): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);

        if ($fleetTrip->status === 'completed') {
            $validated = $request->validate([
                'planned_km' => ['nullable', 'integer', 'min:0'],
                'actual_km' => ['nullable', 'integer', 'min:0'],
            ]);
        } else {
            $validated = $this->validateTripPayload($request, $fleetTrip);
        }

        $fleetTrip->update($validated);
        $this->syncCostLines($request, $fleetTrip);

        return to_route('fleet.trips.show', $fleetTrip);
    }

    public function complete(Request $request, FleetTrip $fleetTrip): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_trips'), 404);

        $request->validate([
            'actual_km' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->filled('actual_km')) {
            $fleetTrip->update(['actual_km' => (int) $request->input('actual_km')]);
        }

        $this->fleetTripService->completeTrip($fleetTrip);

        return to_route('fleet.trips.show', $fleetTrip);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTripPayload(Request $request, ?FleetTrip $existing = null): array
    {
        $orderId = (int) $request->input('order_id');

        return $request->validate([
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
            'order_leg_stage' => ['required', 'string', 'max:50'],
            'carrier_slot' => [
                'nullable',
                'integer',
                'min:1',
                'max:9',
                Rule::unique('fleet_trips', 'carrier_slot')
                    ->where(fn ($query) => $query
                        ->where('order_id', $orderId)
                        ->where('order_leg_stage', $request->input('order_leg_stage')))
                    ->ignore($existing?->id),
            ],
            'fleet_vehicle_id' => ['nullable', 'integer', Rule::exists('fleet_vehicles', 'id')],
            'fleet_driver_id' => ['nullable', 'integer', Rule::exists('fleet_drivers', 'id')],
            'status' => ['nullable', 'string', Rule::in(OwnFleetCatalog::tripStatusCodes())],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'planned_km' => ['nullable', 'integer', 'min:0'],
            'actual_km' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
        ]);
    }

    private function syncCostLines(Request $request, FleetTrip $fleetTrip): void
    {
        if (! Schema::hasTable('fleet_trip_cost_lines')) {
            return;
        }

        $lines = $request->input('cost_lines');
        if (! is_array($lines)) {
            return;
        }

        $fleetTrip->costLines()->delete();

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $amount = (float) ($line['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $fleetTrip->costLines()->create([
                'cost_category' => (string) ($line['cost_category'] ?? 'other'),
                'amount' => $amount,
                'currency' => (string) ($line['currency'] ?? 'RUB'),
                'comment' => isset($line['comment']) ? (string) $line['comment'] : null,
                'occurred_at' => $line['occurred_at'] ?? null,
            ]);
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(OwnFleetCatalog::tripStatusLabels())
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function costCategoryOptions(): array
    {
        return collect(OwnFleetCatalog::costCategoryLabels())
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTripRow(FleetTrip $trip): array
    {
        $vehicle = $trip->vehicle;
        $driver = $trip->driver;

        return [
            'id' => $trip->id,
            'order_id' => $trip->order_id,
            'order_number' => $trip->order?->order_number,
            'order_leg_stage' => $trip->order_leg_stage,
            'carrier_slot' => $trip->carrier_slot,
            'status' => $trip->status,
            'estimated_cost' => $trip->estimated_cost !== null ? (float) $trip->estimated_cost : null,
            'total_cost' => $trip->total_cost !== null ? (float) $trip->total_cost : null,
            'planned_km' => $trip->planned_km,
            'actual_km' => $trip->actual_km,
            'vehicle_label' => $vehicle
                ? trim(implode(' / ', array_filter([$vehicle->tractor_plate, $vehicle->trailer_plate])))
                : null,
            'driver_name' => $driver?->full_name,
            'completed_at' => $trip->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTripDetail(FleetTrip $trip): array
    {
        return [
            ...$this->formatTripRow($trip),
            'fleet_vehicle_id' => $trip->fleet_vehicle_id,
            'fleet_driver_id' => $trip->fleet_driver_id,
            'started_at' => $trip->started_at?->toIso8601String(),
            'cost_lines' => $trip->costLines->map(fn (FleetTripCostLine $line): array => [
                'id' => $line->id,
                'cost_category' => $line->cost_category,
                'amount' => (float) $line->amount,
                'currency' => $line->currency,
                'comment' => $line->comment,
                'occurred_at' => $line->occurred_at?->toDateString(),
            ])->values()->all(),
        ];
    }
}
