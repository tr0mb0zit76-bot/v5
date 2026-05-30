<?php

namespace App\Services;

use App\Http\Controllers\FleetDriverController;
use App\Http\Controllers\FleetVehicleController;
use App\Models\FleetDriverDocument;
use App\Models\FleetVehicleDocument;
use App\Models\OrderPortalInvite;
use App\Support\CarrierPortalFleetResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderCarrierPortalFleetDocumentService
{
    public function __construct(
        private readonly CarrierPortalFleetResolver $fleetResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     * @return list<array<string, mixed>>
     */
    public function fleetDocumentSections(OrderPortalInvite $invite, array $identity): array
    {
        $sections = [];

        if (Schema::hasTable('fleet_vehicles') && Schema::hasTable('fleet_vehicle_documents')) {
            $tractorPlate = trim((string) ($identity['tractor_plate'] ?? ''));
            $trailerPlate = trim((string) ($identity['trailer_plate'] ?? ''));
            $vehicleId = $this->fleetResolver->findVehicleId((int) $invite->contractor_id, $identity);
            $sections[] = [
                'key' => 'vehicle',
                'label' => 'Документы на транспортное средство',
                'hint' => 'ПТС, страховка и другие документы попадут в карточку ТС в CRM.',
                'requires_identity' => 'Укажите госномер тягача или прицепа.',
                'identity_ready' => $tractorPlate !== '' || $trailerPlate !== '',
                'type_options' => FleetVehicleController::documentTypeOptions(),
                'documents' => $vehicleId !== null
                    ? $this->serializeVehicleDocuments($vehicleId)
                    : [],
            ];
        }

        if (Schema::hasTable('fleet_drivers') && Schema::hasTable('fleet_driver_documents')) {
            $driverName = trim((string) ($identity['driver_full_name'] ?? ''));
            $driverId = $this->fleetResolver->findDriverId((int) $invite->contractor_id, $identity);
            $sections[] = [
                'key' => 'driver',
                'label' => 'Документы на водителя',
                'hint' => 'Права, паспорт и другие документы попадут в карточку водителя в CRM.',
                'requires_identity' => 'Укажите ФИО водителя.',
                'identity_ready' => $driverName !== '',
                'type_options' => FleetDriverController::documentTypeOptions(),
                'documents' => $driverId !== null
                    ? $this->serializeDriverDocuments($driverId)
                    : [],
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(
        OrderPortalInvite $invite,
        array $validated,
        UploadedFile $file,
    ): FleetVehicleDocument|FleetDriverDocument {
        $target = (string) ($validated['fleet_target'] ?? '');
        $documentType = (string) ($validated['document_type'] ?? '');
        $allowedTypes = $target === 'vehicle'
            ? collect(FleetVehicleController::documentTypeOptions())->pluck('value')->all()
            : collect(FleetDriverController::documentTypeOptions())->pluck('value')->all();

        if (! in_array($documentType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'document_type' => 'Недопустимый тип документа.',
            ]);
        }

        $identity = [
            'tractor_plate' => $validated['tractor_plate'] ?? null,
            'trailer_plate' => $validated['trailer_plate'] ?? null,
            'tractor_brand' => $validated['tractor_brand'] ?? null,
            'trailer_brand' => $validated['trailer_brand'] ?? null,
            'driver_full_name' => $validated['driver_full_name'] ?? null,
            'driver_phone' => $validated['driver_phone'] ?? null,
            'driver_license' => $validated['driver_license'] ?? null,
        ];

        if ($target === 'vehicle') {
            $vehicleId = $this->fleetResolver->resolveVehicleId((int) $invite->contractor_id, $identity);
            if ($vehicleId === null) {
                throw ValidationException::withMessages([
                    'tractor_plate' => 'Укажите госномер тягача или прицепа перед загрузкой документов на ТС.',
                ]);
            }

            return $this->storeVehicleDocument($vehicleId, (string) $validated['document_type'], $file);
        }

        if ($target === 'driver') {
            $driverId = $this->fleetResolver->resolveDriverId((int) $invite->contractor_id, $identity);
            if ($driverId === null) {
                throw ValidationException::withMessages([
                    'driver_full_name' => 'Укажите ФИО водителя перед загрузкой документов.',
                ]);
            }

            return $this->storeDriverDocument($driverId, (string) $validated['document_type'], $file);
        }

        throw ValidationException::withMessages([
            'fleet_target' => 'Укажите, для кого загружается документ.',
        ]);
    }

    private function storeVehicleDocument(int $vehicleId, string $documentType, UploadedFile $file): FleetVehicleDocument
    {
        abort_unless(Schema::hasTable('fleet_vehicle_documents'), 404);

        $path = $file->store('fleet/vehicles/documents', 'public');

        return FleetVehicleDocument::query()->create([
            'fleet_vehicle_id' => $vehicleId,
            'document_type' => $documentType,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => null,
        ]);
    }

    private function storeDriverDocument(int $driverId, string $documentType, UploadedFile $file): FleetDriverDocument
    {
        abort_unless(Schema::hasTable('fleet_driver_documents'), 404);

        $path = $file->store('fleet/drivers/documents', 'public');

        return FleetDriverDocument::query()->create([
            'fleet_driver_id' => $driverId,
            'document_type' => $documentType,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeVehicleDocuments(int $vehicleId): array
    {
        return FleetVehicleDocument::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetVehicleDocument $document): array => $this->serializeFleetDocument($document, FleetVehicleController::documentTypeOptions()))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeDriverDocuments(int $driverId): array
    {
        return FleetDriverDocument::query()
            ->where('fleet_driver_id', $driverId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetDriverDocument $document): array => $this->serializeFleetDocument($document, FleetDriverController::documentTypeOptions()))
            ->all();
    }

    /**
     * @param  list<array{value: string, label: string}>  $typeOptions
     * @return array<string, mixed>
     */
    private function serializeFleetDocument(FleetVehicleDocument|FleetDriverDocument $document, array $typeOptions): array
    {
        $labels = collect($typeOptions)->pluck('label', 'value');

        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'type_label' => (string) ($labels->get($document->document_type) ?? $document->document_type),
            'original_name' => $document->original_name,
        ];
    }
}
