<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFleetDriverDocumentRequest;
use App\Http\Requests\StoreFleetDriverRequest;
use App\Http\Requests\UpdateFleetDriverRequest;
use App\Models\FleetDriver;
use App\Models\FleetDriverDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FleetDriverController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Schema::hasTable('fleet_drivers'), 404);

        return Inertia::render('Fleet/Drivers', $this->indexPayload());
    }

    public function show(Request $request, FleetDriver $fleetDriver): Response
    {
        abort_unless(Schema::hasTable('fleet_drivers'), 404);
        $fleetDriver->load(['carrier:id,name,inn', 'documents']);

        return Inertia::render('Fleet/Drivers', array_merge($this->indexPayload(), [
            'selectedDriver' => $this->formatDriver($fleetDriver),
        ]));
    }

    public function store(StoreFleetDriverRequest $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_drivers'), 404);
        $driver = FleetDriver::query()->create($request->validated());

        return to_route('fleet.drivers.show', $driver);
    }

    public function update(UpdateFleetDriverRequest $request, FleetDriver $fleetDriver): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_drivers'), 404);
        $fleetDriver->update($request->validated());

        return to_route('fleet.drivers.show', $fleetDriver);
    }

    public function storeDocument(StoreFleetDriverDocumentRequest $request, FleetDriver $fleetDriver): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_driver_documents'), 404);
        $file = $request->file('file');
        $path = $file->store('fleet/drivers/documents', 'public');

        $fleetDriver->documents()->create([
            'document_type' => $request->string('document_type')->toString(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return to_route('fleet.drivers.show', $fleetDriver);
    }

    public function destroyDocument(Request $request, FleetDriver $fleetDriver, FleetDriverDocument $fleetDriverDocument): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_driver_documents'), 404);
        abort_unless($fleetDriverDocument->fleet_driver_id === $fleetDriver->id, 404);

        Storage::disk($fleetDriverDocument->disk)->delete($fleetDriverDocument->path);
        $fleetDriverDocument->delete();

        return to_route('fleet.drivers.show', $fleetDriver);
    }

    public function downloadDocument(Request $request, FleetDriver $fleetDriver, FleetDriverDocument $fleetDriverDocument): BinaryFileResponse
    {
        abort_unless(Schema::hasTable('fleet_driver_documents'), 404);
        abort_unless($fleetDriverDocument->fleet_driver_id === $fleetDriver->id, 404);

        return Storage::disk($fleetDriverDocument->disk)->download($fleetDriverDocument->path, $fleetDriverDocument->original_name);
    }

    public function optionsForOrder(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('fleet_drivers'), 404);
        $carrierId = $request->integer('carrier_contractor_id');
        $query = FleetDriver::query()
            ->with('carrier:id,name')
            ->orderByDesc('id');

        if ($carrierId > 0) {
            $query->where('carrier_contractor_id', $carrierId);
        }

        $drivers = $query->limit(300)->get()->map(fn (FleetDriver $d): array => [
            'id' => $d->id,
            'label' => $d->full_name.($d->phone ? ' · '.$d->phone : ''),
            'carrier_contractor_id' => $d->carrier_contractor_id,
        ]);

        return response()->json(['drivers' => $drivers]);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexPayload(): array
    {
        return [
            'drivers' => $this->driverRows(),
            'selectedDriver' => null,
            'driverDocumentTypeOptions' => self::documentTypeOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function documentTypeOptions(): array
    {
        return [
            ['value' => 'passport', 'label' => 'Паспорт'],
            ['value' => 'license', 'label' => 'Водительское удостоверение'],
            ['value' => 'other', 'label' => 'Прочее'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function driverRows(): array
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return [];
        }

        return FleetDriver::query()
            ->with(['carrier:id,name', 'documents:id,fleet_driver_id'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetDriver $d): array => $this->formatDriverSummary($d))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDriverSummary(FleetDriver $driver): array
    {
        return [
            'id' => $driver->id,
            'carrier_contractor_id' => $driver->carrier_contractor_id,
            'carrier_name' => $driver->carrier?->name,
            'full_name' => $driver->full_name,
            'phone' => $driver->phone,
            'passport_series' => $driver->passport_series,
            'passport_number' => $driver->passport_number,
            'documents_count' => $driver->relationLoaded('documents') ? $driver->documents->count() : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDriver(FleetDriver $driver): array
    {
        $driver->loadMissing(['carrier:id,name,inn', 'documents']);

        return [
            'id' => $driver->id,
            'carrier_contractor_id' => $driver->carrier_contractor_id,
            'carrier_name' => $driver->carrier?->name,
            'carrier_inn' => $driver->carrier?->inn,
            'full_name' => $driver->full_name,
            'passport_series' => $driver->passport_series,
            'passport_number' => $driver->passport_number,
            'passport_issued_by' => $driver->passport_issued_by,
            'passport_issued_at' => optional($driver->passport_issued_at)?->format('Y-m-d'),
            'phone' => $driver->phone,
            'license_number' => $driver->license_number,
            'license_categories' => $driver->license_categories,
            'notes' => $driver->notes,
            'documents' => $driver->documents->map(fn (FleetDriverDocument $d): array => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'original_name' => $d->original_name,
                'download_url' => route('fleet.drivers.documents.download', [$driver, $d]),
            ])->values()->all(),
        ];
    }
}
