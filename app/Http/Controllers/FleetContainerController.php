<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFleetContainerDocumentRequest;
use App\Http\Requests\StoreFleetContainerRequest;
use App\Http\Requests\UpdateFleetContainerRequest;
use App\Models\FleetContainer;
use App\Models\FleetContainerDocument;
use App\Support\InlineStoredFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleetContainerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Schema::hasTable('fleet_containers'), 404);

        return Inertia::render('Fleet/Containers', $this->indexPayload());
    }

    public function show(Request $request, FleetContainer $fleetContainer): Response
    {
        abort_unless(Schema::hasTable('fleet_containers'), 404);
        $fleetContainer->load(['owner:id,name,inn', 'documents']);

        return Inertia::render('Fleet/Containers', array_merge($this->indexPayload(), [
            'selectedContainer' => $this->formatContainer($fleetContainer),
        ]));
    }

    public function store(StoreFleetContainerRequest $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_containers'), 404);
        $validated = $request->validated();
        $this->assertUniqueNumber(null, (int) $validated['owner_contractor_id'], (string) $validated['container_number']);

        $container = FleetContainer::query()->create($validated);

        return to_route('fleet.containers.show', $container);
    }

    public function update(UpdateFleetContainerRequest $request, FleetContainer $fleetContainer): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_containers'), 404);
        $validated = $request->validated();
        $this->assertUniqueNumber(
            $fleetContainer,
            (int) $validated['owner_contractor_id'],
            (string) $validated['container_number'],
        );

        $fleetContainer->update($validated);

        return to_route('fleet.containers.show', $fleetContainer);
    }

    public function storeDocument(StoreFleetContainerDocumentRequest $request, FleetContainer $fleetContainer): RedirectResponse
    {
        abort_unless(Schema::hasTable('fleet_container_documents'), 404);
        $file = $request->file('file');
        $path = $file->store('fleet/containers/documents', 'public');

        $fleetContainer->documents()->create([
            'document_type' => $request->string('document_type')->toString(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return to_route('fleet.containers.show', $fleetContainer);
    }

    public function destroyDocument(
        Request $request,
        FleetContainer $fleetContainer,
        FleetContainerDocument $fleetContainerDocument,
    ): RedirectResponse {
        abort_unless(Schema::hasTable('fleet_container_documents'), 404);
        abort_unless($fleetContainerDocument->fleet_container_id === $fleetContainer->id, 404);

        Storage::disk($fleetContainerDocument->disk)->delete($fleetContainerDocument->path);
        $fleetContainerDocument->delete();

        return to_route('fleet.containers.show', $fleetContainer);
    }

    public function downloadDocument(
        Request $request,
        FleetContainer $fleetContainer,
        FleetContainerDocument $fleetContainerDocument,
    ): BinaryFileResponse|StreamedResponse {
        abort_unless(Schema::hasTable('fleet_container_documents'), 404);
        abort_unless($fleetContainerDocument->fleet_container_id === $fleetContainer->id, 404);

        return Storage::disk($fleetContainerDocument->disk)->download(
            $fleetContainerDocument->path,
            $fleetContainerDocument->original_name,
        );
    }

    public function previewDocument(
        Request $request,
        FleetContainer $fleetContainer,
        FleetContainerDocument $fleetContainerDocument,
    ): HttpResponse {
        abort_unless(Schema::hasTable('fleet_container_documents'), 404);
        abort_unless($fleetContainerDocument->fleet_container_id === $fleetContainer->id, 404);

        return InlineStoredFileResponse::fromDisk(
            $fleetContainerDocument->disk,
            $fleetContainerDocument->path,
            $fleetContainerDocument->mime_type,
            $fleetContainerDocument->original_name,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function indexPayload(): array
    {
        return [
            'containers' => $this->containerRows(),
            'selectedContainer' => null,
            'containerDocumentTypeOptions' => self::documentTypeOptions(),
            'containerSizeOptions' => self::sizeOptions(),
            'containerTypeOptions' => self::typeOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function documentTypeOptions(): array
    {
        return [
            ['value' => 'ownership', 'label' => 'Право собственности'],
            ['value' => 'lease_contract', 'label' => 'Договор аренды'],
            ['value' => 'csc_plate', 'label' => 'CSC / табличка'],
            ['value' => 'other', 'label' => 'Прочее'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function sizeOptions(): array
    {
        return [
            ['value' => '20', 'label' => '20′'],
            ['value' => '40', 'label' => '40′'],
            ['value' => '40HC', 'label' => '40′ HC'],
            ['value' => '45', 'label' => '45′'],
            ['value' => 'other', 'label' => 'Другой'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function typeOptions(): array
    {
        return [
            ['value' => 'dry', 'label' => 'Сухой (DC)'],
            ['value' => 'reefer', 'label' => 'Реф'],
            ['value' => 'open_top', 'label' => 'Open Top'],
            ['value' => 'flat_rack', 'label' => 'Flat Rack'],
            ['value' => 'tank', 'label' => 'Танк'],
            ['value' => 'other', 'label' => 'Другой'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function containerRows(): array
    {
        if (! Schema::hasTable('fleet_containers')) {
            return [];
        }

        return FleetContainer::query()
            ->with(['owner:id,name', 'documents:id,fleet_container_id'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetContainer $container): array => $this->formatContainerSummary($container))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContainerSummary(FleetContainer $container): array
    {
        return [
            'id' => $container->id,
            'show_url' => route('fleet.containers.show', $container),
            'owner_contractor_id' => $container->owner_contractor_id,
            'owner_name' => $container->owner?->name,
            'container_number' => $container->container_number,
            'size_code' => $container->size_code,
            'size_label' => $this->sizeLabel($container->size_code),
            'container_type' => $container->container_type,
            'type_label' => $this->typeLabel($container->container_type),
            'documents_count' => $container->relationLoaded('documents') ? $container->documents->count() : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContainer(FleetContainer $container): array
    {
        $container->loadMissing(['owner:id,name,inn', 'documents']);

        return [
            'id' => $container->id,
            'owner_contractor_id' => $container->owner_contractor_id,
            'owner_name' => $container->owner?->name,
            'owner_inn' => $container->owner?->inn,
            'container_number' => $container->container_number,
            'size_code' => $container->size_code,
            'container_type' => $container->container_type,
            'notes' => $container->notes,
            'documents' => $container->documents
                ->map(fn (FleetContainerDocument $document): array => $this->formatContainerDocument($container, $document))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContainerDocument(FleetContainer $container, FleetContainerDocument $document): array
    {
        $typeLabels = collect(self::documentTypeOptions())->pluck('label', 'value');

        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_type_label' => $typeLabels->get($document->document_type, $document->document_type),
            'original_name' => $document->original_name,
            'created_at' => optional($document->created_at)?->toIso8601String(),
            'preview_url' => route('fleet.containers.documents.preview', [$container, $document]),
            'download_url' => route('fleet.containers.documents.download', [$container, $document]),
        ];
    }

    private function sizeLabel(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return collect(self::sizeOptions())->firstWhere('value', $code)['label'] ?? $code;
    }

    private function typeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return collect(self::typeOptions())->firstWhere('value', $type)['label'] ?? $type;
    }

    private function assertUniqueNumber(?FleetContainer $current, int $ownerId, string $number): void
    {
        $exists = FleetContainer::query()
            ->where('owner_contractor_id', $ownerId)
            ->where('container_number', $number)
            ->when($current !== null, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'container_number' => 'Контейнер с таким номером уже есть у этого владельца.',
            ]);
        }
    }
}
