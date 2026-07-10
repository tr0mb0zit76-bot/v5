<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRegistryRequest;
use App\Http\Requests\UpdateDocumentRegistryRequest;
use App\Http\Requests\UpdateOrderDocumentEdoAcknowledgementRequest;
use App\Http\Requests\UpdateOrderEnteredIn1CRequest;
use App\Http\Requests\UpdateOrderTrackReceivedRequest;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\DocumentStorageService;
use App\Services\Mobile\MobileEntityChipService;
use App\Services\OrderClosingDocumentsNotificationService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentEdoAcknowledgementService;
use App\Services\OrderDocumentRequirementService;
use App\Services\Orders\OrderInlineFieldUpdateService;
use App\Support\DocumentRegistryDocumentLabel;
use App\Support\DocumentRegistryGridColumnApplicabilityResolver;
use App\Support\DocumentRegistryOrderAttentionResolver;
use App\Support\OrderClipboardSummaryResolver;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderTrackReceivedRequirementResolver;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DocumentRegistryController extends Controller
{
    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly DocumentStorageService $documentStorage,
        private readonly OrderClosingDocumentsNotificationService $closingDocumentsNotificationService,
        private readonly OrderDocumentRequirementService $documentRequirementService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $search = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->with([
                'documents',
                'client:id,name',
                'carrier:id,name',
                'financialTerms',
                'legs.routePoints',
            ])
            ->orderByDesc('id');

        if (! RoleAccess::isAdminUser($user) && ! $user->isSupervisor()) {
            OrderViewAuthorization::applyOrdersVisibilityScope($query, $user, 'documents');
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
        $clipboardSummaries = app(OrderClipboardSummaryResolver::class)->mapForOrders($orders);
        $trackReceivedFlags = OrderTrackReceivedRequirementResolver::mapFlagsForOrders($orders);
        $columnApplicability = app(DocumentRegistryGridColumnApplicabilityResolver::class)->mapForOrders($orders);
        $attentionFlags = app(DocumentRegistryOrderAttentionResolver::class)->mapForOrders($orders);

        return Inertia::render('Documents/Index', [
            'search' => $search,
            'can_edit_track_received_dates' => RoleAccess::canEditTrackReceivedDates($user),
            'rows' => $orders
                ->map(fn (Order $order): array => $this->serializeRow(
                    $order,
                    $clipboardSummaries[(int) $order->id] ?? '',
                    $trackReceivedFlags[(int) $order->id] ?? [
                        'needs_track_received_date_customer' => false,
                        'needs_track_received_date_carrier' => false,
                    ],
                    $columnApplicability[(int) $order->id] ?? [],
                    $attentionFlags[(int) $order->id] ?? [
                        'missing_documents_after_unloading' => false,
                        'missing_document_labels' => [],
                    ],
                ))
                ->values(),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->client?->name,
            ])->values(),
        ]);
    }

    public function store(StoreDocumentRegistryRequest $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validated();
        $order = Order::query()->findOrFail((int) $payload['order_id']);
        $this->ensureCanManageOrder($request, $order);
        $file = $request->file('file');
        abort_if($file === null, 422);

        try {
            $stored = $this->documentStorage->storeOrderUpload($file, $order->id);
        } catch (RuntimeException $exception) {
            return $this->storageFailureResponse($request, $exception);
        }

        $metadata = [
            'party' => $payload['party'],
            'flow' => 'uploaded',
            'storage_driver' => $stored['storage_driver'],
        ];

        if (filled($payload['order_leg_stage'] ?? null)) {
            $metadata['order_leg_stage'] = trim((string) $payload['order_leg_stage']);
        }

        if (isset($payload['carrier_contractor_id']) && (int) $payload['carrier_contractor_id'] > 0) {
            $metadata['carrier_contractor_id'] = (int) $payload['carrier_contractor_id'];
        }

        if (isset($payload['carrier_slot']) && (int) $payload['carrier_slot'] > 0) {
            $metadata['carrier_slot'] = (int) $payload['carrier_slot'];
        }

        if (isset($payload['contractor_id']) && (int) $payload['contractor_id'] > 0) {
            $metadata['contractor_id'] = (int) $payload['contractor_id'];
        }

        if (filled($payload['requirement_slot_key'] ?? null)) {
            $metadata['requirement_slot_key'] = trim((string) $payload['requirement_slot_key']);
        }

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

        $document = OrderDocument::query()->create($attributes);

        $this->orderCompensationService->recalculateImpactedPeriods($order);
        $this->closingDocumentsNotificationService->maybeNotify($order->fresh());

        $message = 'Документ добавлен в реестр и карточку заказа.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'document' => app(MobileEntityChipService::class)->chipFromOrderDocument($document, $order),
            ]);
        }

        return to_route('documents.index')->with('flash', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function update(UpdateDocumentRegistryRequest $request, OrderDocument $document): RedirectResponse|JsonResponse
    {
        $payload = $request->validated();
        $order = Order::query()->findOrFail((int) $payload['order_id']);
        $this->ensureCanManageOrder($request, $order);
        $file = $request->file('file');

        $metadata = array_merge((array) ($document->metadata ?? []), [
            'party' => $payload['party'],
            'flow' => 'uploaded',
        ]);

        if (isset($payload['contractor_id']) && (int) $payload['contractor_id'] > 0) {
            $metadata['contractor_id'] = (int) $payload['contractor_id'];
        } else {
            unset($metadata['contractor_id']);
        }

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

            try {
                $stored = $this->documentStorage->storeOrderUpload($file, $order->id);
            } catch (RuntimeException $exception) {
                return $this->storageFailureResponse($request, $exception);
            }

            $attrs['metadata']['storage_driver'] = $stored['storage_driver'];
            $attrs['original_name'] = $stored['original_name'];
            $attrs['file_path'] = $stored['file_path'];
            $attrs['file_size'] = $stored['file_size'];
            $attrs['mime_type'] = $stored['mime_type'];
            $attrs['uploaded_by'] = $request->user()?->id;
        }

        $document->fill($attrs)->save();
        $this->orderCompensationService->recalculateImpactedPeriods($order);
        $this->closingDocumentsNotificationService->maybeNotify($order->fresh());

        $message = 'Документ обновлён.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return to_route('documents.index')->with('flash', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function updateEnteredIn1C(UpdateOrderEnteredIn1CRequest $request, Order $order): JsonResponse
    {
        $this->ensureCanManageOrder($request, $order);
        abort_unless(Schema::hasColumn('orders', 'accounting_handoff_at'), 404);

        $entered = $request->validated('entered_in_1c') === 'да';

        if ($entered) {
            $order->forceFill([
                'accounting_handoff_at' => $order->accounting_handoff_at ?? now(),
                'accounting_handoff_by' => $request->user()->id,
            ]);
        } else {
            $order->forceFill([
                'accounting_handoff_at' => null,
                'accounting_handoff_by' => null,
            ]);
        }

        $order->save();

        return response()->json([
            'entered_in_1c' => $entered ? 'да' : 'нет',
        ]);
    }

    public function updateTrackReceived(
        UpdateOrderTrackReceivedRequest $request,
        Order $order,
        OrderInlineFieldUpdateService $orderInlineFieldUpdateService,
    ): JsonResponse {
        abort_unless(RoleAccess::canEditTrackReceivedDates($request->user()), 403);
        $this->ensureCanManageOrder($request, $order);

        $field = (string) $request->validated('field');
        $order->loadMissing('financialTerms');
        $financialTerm = $order->financialTerms->first();
        $flags = OrderTrackReceivedRequirementResolver::flagsForOrder($order, $financialTerm);

        $needsKey = $field === 'track_received_date_customer'
            ? 'needs_track_received_date_customer'
            : 'needs_track_received_date_carrier';

        abort_unless((bool) ($flags[$needsKey] ?? false), 422, 'Для этого заказа дата получения по выбранной стороне не требуется.');

        abort_unless(
            Schema::hasColumn('orders', $field),
            404,
        );

        $updated = $orderInlineFieldUpdateService->apply(
            $request->user(),
            $order,
            $field,
            $request->validated('value'),
        );

        $dateValue = $updated->{$field};

        return response()->json([
            'field' => $field,
            'value' => $dateValue !== null ? $dateValue->toDateString() : null,
        ]);
    }

    public function updateEdoAcknowledgement(
        UpdateOrderDocumentEdoAcknowledgementRequest $request,
        Order $order,
        OrderDocumentEdoAcknowledgementService $edoAcknowledgementService,
    ): JsonResponse {
        abort_unless(RoleAccess::canEditDocumentEdoAcknowledgements($request->user()), 403);
        $this->ensureCanManageOrder($request, $order);

        try {
            $acknowledgement = $edoAcknowledgementService->upsertForOrder(
                $order,
                $request->validated(),
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'acknowledgement' => [
                'id' => $acknowledgement->id,
                'party' => $acknowledgement->party,
                'document_type' => $acknowledgement->document_type,
                'slot_key' => $acknowledgement->slot_key,
                'contractor_id' => $acknowledgement->contractor_id > 0 ? $acknowledgement->contractor_id : null,
                'received_via_edo' => (bool) $acknowledgement->received_via_edo,
                'document_number' => $acknowledgement->document_number,
                'document_date' => optional($acknowledgement->document_date)?->toDateString(),
            ],
            'required_document_checklist' => $this->documentRequirementService
                ->checklistForOrder($order->fresh(['documents', 'edoAcknowledgements'])),
            'column_edo_fulfilment' => $edoAcknowledgementService->closingColumnEdoFlags($order->fresh(['documents', 'edoAcknowledgements'])),
        ]);
    }

    public function destroy(Request $request, OrderDocument $document): RedirectResponse|JsonResponse
    {
        $order = Order::query()->findOrFail((int) $document->order_id);
        $this->ensureCanManageOrder($request, $order);

        if ($this->orderDocumentIsPrintWorkflow($document)) {
            abort_unless(
                $request->user() !== null,
                403,
            );

            $document->delete();
            $this->orderCompensationService->recalculateImpactedPeriods($order);

            $message = 'Печатная форма удалена из заказа.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'message' => $message]);
            }

            return back()->with('flash', ['type' => 'success', 'message' => $message]);
        }

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

        $document->delete();
        $this->orderCompensationService->recalculateImpactedPeriods($order);

        $message = 'Документ удалён.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => $message]);
    }

    /**
     * @param  array{
     *     needs_track_received_date_customer: bool,
     *     needs_track_received_date_carrier: bool,
     * }  $trackReceivedFlags
     * @param  array<string, bool>  $columnApplicability
     * @param  array{
     *     missing_documents_after_unloading: bool,
     *     missing_document_labels: list<string>,
     * }  $attentionFlags
     * @return array<string, mixed>
     */
    private function serializeRow(
        Order $order,
        string $clipboardSummary = '',
        array $trackReceivedFlags = [],
        array $columnApplicability = [],
        array $attentionFlags = [],
    ): array {
        $documents = $order->documents ?? collect();
        $etrn = $this->serializeEtrnSummary($documents);
        $contractorNamesById = DocumentRegistryDocumentLabel::contractorNamesByIdFromDocuments($documents);
        $columnEdoFulfilment = app(OrderDocumentEdoAcknowledgementService::class)
            ->closingColumnEdoFlags($order);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?: '#'.$order->id,
            'order_edit_url' => route('orders.edit', $order).'?tab=documents',
            'missing_documents_after_unloading' => (bool) ($attentionFlags['missing_documents_after_unloading'] ?? false),
            'missing_document_labels' => array_values($attentionFlags['missing_document_labels'] ?? []),
            'entered_in_1c' => $this->serializeEnteredIn1C($order),
            'track_received_date_customer' => Schema::hasColumn('orders', 'track_received_date_customer')
                ? optional($order->track_received_date_customer)?->toDateString()
                : null,
            'track_received_date_carrier' => Schema::hasColumn('orders', 'track_received_date_carrier')
                ? optional($order->track_received_date_carrier)?->toDateString()
                : null,
            'needs_track_received_date_customer' => (bool) ($trackReceivedFlags['needs_track_received_date_customer'] ?? false),
            'needs_track_received_date_carrier' => (bool) ($trackReceivedFlags['needs_track_received_date_carrier'] ?? false),
            'column_applicable' => $columnApplicability,
            'column_edo_fulfilment' => $columnEdoFulfilment,
            'clipboard_summary' => $clipboardSummary,
            'customer_invoice' => $this->serializeColumnDocs($order, $documents, 'invoice', 'customer', $contractorNamesById),
            'customer_upd' => $this->serializeColumnDocs($order, $documents, 'upd', 'customer', $contractorNamesById),
            'customer_act' => $this->serializeColumnDocs($order, $documents, 'act', 'customer', $contractorNamesById),
            'customer_invoice_factura' => $this->serializeColumnDocs($order, $documents, 'invoice_factura', 'customer', $contractorNamesById),
            'customer_request' => $this->serializeColumnDocs($order, $documents, 'request', 'customer', $contractorNamesById),
            'customer_contract_request' => $this->serializeColumnDocs($order, $documents, 'contract_request', 'customer', $contractorNamesById),
            'carrier_invoice' => $this->serializeColumnDocs($order, $documents, 'invoice', 'carrier', $contractorNamesById),
            'carrier_upd' => $this->serializeColumnDocs($order, $documents, 'upd', 'carrier', $contractorNamesById),
            'carrier_act' => $this->serializeColumnDocs($order, $documents, 'act', 'carrier', $contractorNamesById),
            'carrier_invoice_factura' => $this->serializeColumnDocs($order, $documents, 'invoice_factura', 'carrier', $contractorNamesById),
            'carrier_request' => $this->serializeColumnDocs($order, $documents, 'request', 'carrier', $contractorNamesById),
            'carrier_contract_request' => $this->serializeColumnDocs($order, $documents, 'contract_request', 'carrier', $contractorNamesById),
            'transport_docs' => $this->serializeTransportDocs($order, $documents, $contractorNamesById),
            'etrn_status' => $etrn['status'],
            'etrn_external_id' => $etrn['external_id'],
            'other_docs' => $this->serializeOtherDocs($order, $documents, $contractorNamesById),
        ];
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @param  array<int, string>  $contractorNamesById
     * @return list<array{id: int, label: string, preview_url: string, order_url: string}>
     */
    private function serializeColumnDocs(Order $order, $documents, string $type, string $party, array $contractorNamesById = []): array
    {
        return $documents
            ->filter(function (OrderDocument $doc) use ($type, $party): bool {
                $meta = (array) ($doc->metadata ?? []);

                return $doc->type === $type && ($meta['party'] ?? 'internal') === $party;
            })
            ->sortBy(static function (OrderDocument $doc): array {
                $meta = (array) ($doc->metadata ?? []);

                return [
                    (int) ($meta['carrier_slot'] ?? 999),
                    $doc->id,
                ];
            })
            ->map(function (OrderDocument $doc) use ($order, $contractorNamesById): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);
                $meta = (array) ($doc->metadata ?? []);

                return [
                    'id' => $doc->id,
                    'label' => DocumentRegistryDocumentLabel::build($doc, $meta, $contractorNamesById),
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
    /**
     * @param  array<int, string>  $contractorNamesById
     */
    private function serializeTransportDocs(Order $order, $documents, array $contractorNamesById = []): array
    {
        $transportTypes = ['waybill', 'etrn', 'cmr', 'packing_list', 'customs_declaration'];

        return $documents
            ->filter(fn (OrderDocument $doc): bool => in_array($doc->type, $transportTypes, true))
            ->map(function (OrderDocument $doc) use ($order, $contractorNamesById): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);
                $meta = (array) ($doc->metadata ?? []);

                return [
                    'id' => $doc->id,
                    'type' => (string) $doc->type,
                    'label' => DocumentRegistryDocumentLabel::build($doc, $meta, $contractorNamesById)
                        ?: ($doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type))),
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
    /**
     * @param  array<int, string>  $contractorNamesById
     */
    private function serializeOtherDocs(Order $order, $documents, array $contractorNamesById = []): array
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
            ->map(function (OrderDocument $doc) use ($order, $contractorNamesById): array {
                $preview = $this->resolveOrderDocumentPreviewUrl($order, $doc);
                $meta = (array) ($doc->metadata ?? []);

                return [
                    'id' => $doc->id,
                    'label' => DocumentRegistryDocumentLabel::build($doc, $meta, $contractorNamesById)
                        ?: ($doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type))),
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
        abort_unless(
            OrderDocumentAccessAuthorization::userMayManageDocuments($request->user(), $order),
            403,
        );
    }

    private function serializeEnteredIn1C(Order $order): string
    {
        if (! Schema::hasColumn('orders', 'accounting_handoff_at')) {
            return 'нет';
        }

        return $order->accounting_handoff_at !== null ? 'да' : 'нет';
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

    private function storageFailureResponse(Request $request, RuntimeException $exception): RedirectResponse|JsonResponse
    {
        $message = $exception->getMessage();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 502);
        }

        return back()->withErrors(['file' => $message]);
    }
}
