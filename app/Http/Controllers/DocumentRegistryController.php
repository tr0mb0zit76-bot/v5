<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRegistryRequest;
use App\Http\Requests\UpdateDocumentRegistryRequest;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\DocumentStorageService;
use App\Services\OrderCompensationService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRegistryController extends Controller
{
    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'documents');
        $search = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->with(['documents', 'client:id,name', 'carrier:id,name'])
            ->orderByDesc('id');

        if ($user->role?->name !== 'admin' && $scope !== 'all') {
            $query->where('manager_id', $user->id);
        }

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('order_customer_number', 'like', '%'.$search.'%');
                if (preg_match('/^\d+$/', $search) === 1) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orders = $query->limit(400)->get();

        return Inertia::render('Documents/Index', [
            'search' => $search,
            'rows' => $orders->map(fn (Order $order): array => $this->serializeRow($order))->values(),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->client?->name,
            ])->values(),
        ]);
    }

    public function store(StoreDocumentRegistryRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $order = Order::query()->findOrFail((int) $payload['order_id']);
        $this->ensureCanManageOrder($request, $order);
        $file = $request->file('file');
        abort_if($file === null, 422);

        $stored = $this->documentStorage->storeOrderUpload($file);
        $metadata = [
            'party' => $payload['party'],
            'flow' => 'uploaded',
            'storage_driver' => $stored['storage_driver'],
        ];

        $attributes = [
            'order_id' => $order->id,
            'type' => $payload['type'],
            'number' => $this->nullableTrimmedString($payload['number'] ?? null),
            'document_date' => $this->nullableDateString($payload['document_date'] ?? null),
            'original_name' => $stored['original_name'],
            'file_path' => $stored['file_path'],
            'file_size' => $stored['file_size'],
            'mime_type' => $stored['mime_type'],
            'uploaded_by' => $request->user()?->id,
            'status' => $payload['status'],
            'metadata' => $metadata,
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ];

        OrderDocument::query()->create($attributes);

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return to_route('documents.index')->with('flash', [
            'type' => 'success',
            'message' => 'Документ добавлен в реестр и карточку заказа.',
        ]);
    }

    public function update(UpdateDocumentRegistryRequest $request, OrderDocument $document): RedirectResponse
    {
        $payload = $request->validated();
        $order = Order::query()->findOrFail((int) $payload['order_id']);
        $this->ensureCanManageOrder($request, $order);
        $file = $request->file('file');

        $metadata = array_merge((array) ($document->metadata ?? []), [
            'party' => $payload['party'],
            'flow' => 'uploaded',
        ]);

        $attrs = [
            'order_id' => $order->id,
            'type' => $payload['type'],
            'number' => $this->nullableTrimmedString($payload['number'] ?? null),
            'document_date' => $this->nullableDateString($payload['document_date'] ?? null),
            'status' => $payload['status'],
            'metadata' => $metadata,
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ];

        if ($file !== null) {
            $oldPath = $document->file_path;
            $oldDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            if (filled($oldPath)) {
                $this->documentStorage->delete(
                    $oldPath,
                    $oldDriver === DocumentStorageService::DRIVER_NEXTCLOUD
                        ? DocumentStorageService::DRIVER_NEXTCLOUD
                        : DocumentStorageService::DRIVER_LOCAL,
                );
            }

            $stored = $this->documentStorage->storeOrderUpload($file);
            $attrs['metadata']['storage_driver'] = $stored['storage_driver'];
            $attrs['original_name'] = $stored['original_name'];
            $attrs['file_path'] = $stored['file_path'];
            $attrs['file_size'] = $stored['file_size'];
            $attrs['mime_type'] = $stored['mime_type'];
            $attrs['uploaded_by'] = $request->user()?->id;
        }

        $document->fill($attrs)->save();
        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return to_route('documents.index')->with('flash', [
            'type' => 'success',
            'message' => 'Документ обновлён.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(Order $order): array
    {
        $documents = $order->documents ?? collect();
        $etrn = $this->serializeEtrnSummary($documents);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?: '#'.$order->id,
            'order_edit_url' => route('orders.edit', $order).'?tab=documents',
            'customer_invoice' => $this->serializeColumnDocs($order, $documents, 'invoice', 'customer'),
            'customer_upd' => $this->serializeColumnDocs($order, $documents, 'upd', 'customer'),
            'customer_act' => $this->serializeColumnDocs($order, $documents, 'act', 'customer'),
            'customer_invoice_factura' => $this->serializeColumnDocs($order, $documents, 'invoice_factura', 'customer'),
            'customer_request' => $this->serializeColumnDocs($order, $documents, 'request', 'customer'),
            'customer_contract_request' => $this->serializeColumnDocs($order, $documents, 'contract_request', 'customer'),
            'carrier_invoice' => $this->serializeColumnDocs($order, $documents, 'invoice', 'carrier'),
            'carrier_upd' => $this->serializeColumnDocs($order, $documents, 'upd', 'carrier'),
            'carrier_act' => $this->serializeColumnDocs($order, $documents, 'act', 'carrier'),
            'carrier_invoice_factura' => $this->serializeColumnDocs($order, $documents, 'invoice_factura', 'carrier'),
            'carrier_request' => $this->serializeColumnDocs($order, $documents, 'request', 'carrier'),
            'carrier_contract_request' => $this->serializeColumnDocs($order, $documents, 'contract_request', 'carrier'),
            'transport_docs' => $this->serializeTransportDocs($order, $documents),
            'etrn_status' => $etrn['status'],
            'etrn_external_id' => $etrn['external_id'],
            'other_docs' => $this->serializeOtherDocs($order, $documents),
        ];
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, label: string, preview_url: string, order_url: string}>
     */
    private function serializeColumnDocs(Order $order, $documents, string $type, string $party): array
    {
        return $documents
            ->filter(function (OrderDocument $doc) use ($type, $party): bool {
                $meta = (array) ($doc->metadata ?? []);

                return $doc->type === $type && ($meta['party'] ?? 'internal') === $party;
            })
            ->map(function (OrderDocument $doc) use ($order): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);

                return [
                    'id' => $doc->id,
                    'label' => $doc->number ?: ($doc->original_name ?: 'Без номера'),
                    'preview_url' => $preview,
                    'order_url' => $preview,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, type: string, label: string, preview_url: string, order_url: string}>
     */
    private function serializeTransportDocs(Order $order, $documents): array
    {
        $transportTypes = ['waybill', 'etrn', 'cmr', 'packing_list', 'customs_declaration'];

        return $documents
            ->filter(fn (OrderDocument $doc): bool => in_array($doc->type, $transportTypes, true))
            ->map(function (OrderDocument $doc) use ($order): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);

                return [
                    'id' => $doc->id,
                    'type' => (string) $doc->type,
                    'label' => $doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type)),
                    'preview_url' => $preview,
                    'order_url' => $preview,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, label: string, preview_url: string, order_url: string}>
     */
    private function serializeOtherDocs(Order $order, $documents): array
    {
        $structuredTypes = ['invoice', 'upd', 'act', 'invoice_factura', 'waybill', 'etrn', 'cmr', 'packing_list', 'customs_declaration'];
        $partySplitTypes = ['request', 'contract_request'];

        return $documents
            ->filter(function (OrderDocument $doc) use ($structuredTypes, $partySplitTypes): bool {
                $type = $doc->type;
                $party = (array) ($doc->metadata ?? [])['party'] ?? 'internal';

                if (in_array($type, $partySplitTypes, true) && in_array($party, ['customer', 'carrier'], true)) {
                    return false;
                }

                return ! in_array($type, $structuredTypes, true);
            })
            ->map(function (OrderDocument $doc) use ($order): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);

                return [
                    'id' => $doc->id,
                    'label' => $doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type)),
                    'preview_url' => $preview,
                    'order_url' => $preview,
                ];
            })
            ->values()
            ->all();
    }

    private function resolveOrderDocumentPreviewUrl(Order $order, OrderDocument $doc): string
    {
        if ($this->orderDocumentIsPrintWorkflow($doc)) {
            if (filled($doc->file_path) || filled($doc->generated_pdf_path)) {
                return route('orders.documents.preview-draft', [$order, $doc]);
            }

            return route('orders.edit', $order).'?tab=documents';
        }

        if (filled($doc->file_path)) {
            return route('orders.documents.preview-uploaded', [$order, $doc]);
        }

        return route('orders.edit', $order).'?tab=documents';
    }

    private function orderDocumentIsPrintWorkflow(OrderDocument $document): bool
    {
        if (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template') {
            return true;
        }

        return data_get($document->metadata, 'flow') === 'print_template_workflow';
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return array{status: string, external_id: string}
     */
    private function serializeEtrnSummary($documents): array
    {
        $etrn = $documents
            ->first(fn (OrderDocument $document): bool => $document->type === 'etrn');

        if (! $etrn instanceof OrderDocument) {
            return [
                'status' => '—',
                'external_id' => '—',
            ];
        }

        $metadata = is_array($etrn->metadata) ? $etrn->metadata : [];
        $epd = is_array($metadata['epd'] ?? null) ? $metadata['epd'] : [];
        $status = (string) ($epd['gis_status'] ?? $etrn->status ?? '');
        $externalId = (string) ($epd['external_id'] ?? '');

        return [
            'status' => $this->etrnStatusLabel($status),
            'external_id' => $externalId !== '' ? $externalId : '—',
        ];
    }

    private function etrnStatusLabel(string $status): string
    {
        return match ($status) {
            'draft', 'draft_incomplete' => 'Черновик',
            'ready_for_1c' => 'Готов к 1С',
            'pending' => 'Ожидает',
            'sent', 'sent_to_epd' => 'Отправлен',
            'signed', 'completed', 'done' => 'Подписан',
            'rejected' => 'Отклонен',
            'cancelled' => 'Отменен',
            default => $status !== '' ? $status : '—',
        };
    }

    private function ensureCanManageOrder(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        $docScope = RoleAccess::resolveVisibilityScopeForUser($user, 'documents');

        if ($docScope === 'all') {
            return;
        }

        abort_unless((int) $order->manager_id === (int) $user->id, 403);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return strlen($trimmed) >= 10 ? substr($trimmed, 0, 10) : $trimmed;
    }
}
