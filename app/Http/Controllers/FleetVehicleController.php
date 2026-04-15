<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFleetVehicleDocumentRequest;
use App\Http\Requests\StoreFleetVehicleRequest;
use App\Http\Requests\UpdateFleetVehicleRequest;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FleetVehicleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Schema::hasTable('fleet_vehicles'), 404);

        return Inertia::render('Fleet/Vehicles', $this->indexPayload());
    }

    public function show(Request $request, FleetVehicle $fleetVehicle): Response
    {
        abort_unless(Schema::hasTable('fleet_vehicles'), 404);
        $fleetVehicle->load(['owner:id,name,inn', 'documents']);

        return Inertia::render('Fleet/Vehicles', array_merge($this->indexPayload(), [
            'selectedVehicle' => $this->formatVehicle($fleetVehicle),
        ]));
    }

    public function store(StoreFleetVehicleRequest $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_vehicles'), 404);
        $validated = $request->validated();
        $vehicle = FleetVehicle::query()->create($validated);

        return to_route('fleet.vehicles.show', $vehicle);
    }

    public function update(UpdateFleetVehicleRequest $request, FleetVehicle $fleetVehicle): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_vehicles'), 404);
        $fleetVehicle->update($request->validated());

        return to_route('fleet.vehicles.show', $fleetVehicle);
    }

    public function storeDocument(StoreFleetVehicleDocumentRequest $request, FleetVehicle $fleetVehicle): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_vehicle_documents'), 404);
        $file = $request->file('file');
        $path = $file->store('fleet/vehicles/documents', 'public');

        $fleetVehicle->documents()->create([
            'document_type' => $request->string('document_type')->toString(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return to_route('fleet.vehicles.show', $fleetVehicle);
    }

    public function destroyDocument(Request $request, FleetVehicle $fleetVehicle, FleetVehicleDocument $fleetVehicleDocument): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_vehicle_documents'), 404);
        abort_unless($fleetVehicleDocument->fleet_vehicle_id === $fleetVehicle->id, 404);

        Storage::disk($fleetVehicleDocument->disk)->delete($fleetVehicleDocument->path);
        $fleetVehicleDocument->delete();

        return to_route('fleet.vehicles.show', $fleetVehicle);
    }

    public function downloadDocument(Request $request, FleetVehicle $fleetVehicle, FleetVehicleDocument $fleetVehicleDocument): BinaryFileResponse
    {
        abort_unless(Schema::hasTable('fleet_vehicle_documents'), 404);
        abort_unless($fleetVehicleDocument->fleet_vehicle_id === $fleetVehicle->id, 404);

        return Storage::disk($fleetVehicleDocument->disk)->download($fleetVehicleDocument->path, $fleetVehicleDocument->original_name);
    }

    public function optionsForOrder(Request $request): JsonResponse
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return response()->json(['vehicles' => []]);
        }

        $ownerId = $request->integer('owner_contractor_id');
        $query = FleetVehicle::query()
            ->with('owner:id,name')
            ->orderByDesc('id');

        if ($ownerId > 0) {
            $query->where('owner_contractor_id', $ownerId);
        }

        $vehicles = $query->limit(300)->get()->map(fn (FleetVehicle $v): array => [
            'id' => $v->id,
            'label' => $this->vehicleOptionLabel($v),
            'owner_contractor_id' => $v->owner_contractor_id,
        ]);

        return response()->json(['vehicles' => $vehicles]);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexPayload(): array
    {
        return [
            'vehicles' => $this->vehicleRows(),
            'selectedVehicle' => null,
            'vehicleDocumentTypeOptions' => self::documentTypeOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function documentTypeOptions(): array
    {
        return [
            ['value' => 'pts', 'label' => 'ПТС'],
            ['value' => 'lease_contract', 'label' => 'Договор аренды'],
            ['value' => 'leasing', 'label' => 'Лизинг'],
            ['value' => 'insurance', 'label' => 'Страховка'],
            ['value' => 'other', 'label' => 'Прочее'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vehicleRows(): array
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return [];
        }

        return FleetVehicle::query()
            ->with(['owner:id,name', 'documents:id,fleet_vehicle_id'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetVehicle $v): array => $this->formatVehicleSummary($v))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVehicleSummary(FleetVehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'owner_contractor_id' => $vehicle->owner_contractor_id,
            'owner_name' => $vehicle->owner?->name,
            'tractor_brand' => $vehicle->tractor_brand,
            'trailer_brand' => $vehicle->trailer_brand,
            'tractor_plate' => $vehicle->tractor_plate,
            'trailer_plate' => $vehicle->trailer_plate,
            'documents_count' => $vehicle->relationLoaded('documents') ? $vehicle->documents->count() : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVehicle(FleetVehicle $vehicle): array
    {
        $vehicle->loadMissing(['owner:id,name,inn', 'documents']);

        return [
            'id' => $vehicle->id,
            'owner_contractor_id' => $vehicle->owner_contractor_id,
            'owner_name' => $vehicle->owner?->name,
            'owner_inn' => $vehicle->owner?->inn,
            'tractor_brand' => $vehicle->tractor_brand,
            'trailer_brand' => $vehicle->trailer_brand,
            'tractor_plate' => $vehicle->tractor_plate,
            'trailer_plate' => $vehicle->trailer_plate,
            'notes' => $vehicle->notes,
            'documents' => $vehicle->documents->map(fn (FleetVehicleDocument $d): array => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'original_name' => $d->original_name,
                'download_url' => route('fleet.vehicles.documents.download', [$vehicle, $d]),
            ])->values()->all(),
        ];
    }

    private function vehicleOptionLabel(FleetVehicle $vehicle): string
    {
        $parts = array_filter([
            $vehicle->tractor_plate,
            $vehicle->trailer_plate,
            $vehicle->tractor_brand,
        ]);

        if ($parts === []) {
            return 'ТС #'.$vehicle->id;
        }

        return implode(' · ', $parts);
    }
}
