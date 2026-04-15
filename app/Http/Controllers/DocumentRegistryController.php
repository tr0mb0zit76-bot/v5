<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRegistryRequest;
use App\Http\Requests\UpdateDocumentRegistryRequest;
use App\Models\Order;
use App\Models\OrderDocument;
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
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->loadMissing('role');
        $scope = RoleAccess::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'orders');
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

        $storedPath = $file?->store('order-documents');
        $metadata = [
            'party' => $payload['party'],
            'flow' => 'uploaded',
        ];

        $attributes = [
            'order_id' => $order->id,
            'type' => $payload['type'],
            'number' => $this->nullableTrimmedString($payload['number'] ?? null),
            'document_date' => $this->nullableDateString($payload['document_date'] ?? null),
            'original_name' => $file?->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_size' => $file?->getSize(),
            'mime_type' => $file?->getMimeType(),
            'uploaded_by' => $request->user()?->id,
            'status' => $payload['status'],
            'metadata' => $metadata,
        ];

        if (Schema::hasColumn('order_documents', 'entity_type')) {
            $attributes['entity_type'] = 'order';
        }

        if (Schema::hasColumn('order_documents', 'entity_id')) {
            $attributes['entity_id'] = $order->id;
        }

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

        $attrs = [
            'order_id' => $order->id,
            'type' => $payload['type'],
            'number' => $this->nullableTrimmedString($payload['number'] ?? null),
            'document_date' => $this->nullableDateString($payload['document_date'] ?? null),
            'status' => $payload['status'],
            'metadata' => array_merge((array) ($document->metadata ?? []), [
                'party' => $payload['party'],
                'flow' => 'uploaded',
            ]),
        ];

        if (Schema::hasColumn('order_documents', 'entity_type')) {
            $attrs['entity_type'] = 'order';
        }

        if (Schema::hasColumn('order_documents', 'entity_id')) {
            $attrs['entity_id'] = $order->id;
        }

        if ($file !== null) {
            $attrs['original_name'] = $file->getClientOriginalName();
            $attrs['file_path'] = $file->store('order-documents');
            $attrs['file_size'] = $file->getSize();
            $attrs['mime_type'] = $file->getMimeType();
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

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?: '#'.$order->id,
            'order_edit_url' => route('orders.edit', $order).'?tab=documents',
            'customer_invoice' => $this->serializeColumnDocs($documents, 'invoice', 'customer'),
            'customer_upd' => $this->serializeColumnDocs($documents, 'upd', 'customer'),
            'customer_act' => $this->serializeColumnDocs($documents, 'act', 'customer'),
            'customer_invoice_factura' => $this->serializeColumnDocs($documents, 'invoice_factura', 'customer'),
            'customer_request' => $this->serializeColumnDocs($documents, 'request', 'customer'),
            'customer_contract_request' => $this->serializeColumnDocs($documents, 'contract_request', 'customer'),
            'carrier_invoice' => $this->serializeColumnDocs($documents, 'invoice', 'carrier'),
            'carrier_upd' => $this->serializeColumnDocs($documents, 'upd', 'carrier'),
            'carrier_act' => $this->serializeColumnDocs($documents, 'act', 'carrier'),
            'carrier_invoice_factura' => $this->serializeColumnDocs($documents, 'invoice_factura', 'carrier'),
            'carrier_request' => $this->serializeColumnDocs($documents, 'request', 'carrier'),
            'carrier_contract_request' => $this->serializeColumnDocs($documents, 'contract_request', 'carrier'),
            'transport_docs' => $this->serializeTransportDocs($documents),
            'other_docs' => $this->serializeOtherDocs($documents),
        ];
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, label: string, order_url: string}>
     */
    private function serializeColumnDocs($documents, string $type, string $party): array
    {
        return $documents
            ->filter(function (OrderDocument $doc) use ($type, $party): bool {
                $meta = (array) ($doc->metadata ?? []);

                return $doc->type === $type && ($meta['party'] ?? 'internal') === $party;
            })
            ->map(fn (OrderDocument $doc): array => [
                'id' => $doc->id,
                'label' => $doc->number ?: ($doc->original_name ?: 'Без номера'),
                'order_url' => route('orders.edit', (int) $doc->order_id).'?tab=documents',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, label: string, order_url: string}>
     */
    private function serializeTransportDocs($documents): array
    {
        $transportTypes = ['waybill', 'cmr', 'packing_list', 'customs_declaration'];

        return $documents
            ->filter(fn (OrderDocument $doc): bool => in_array($doc->type, $transportTypes, true))
            ->map(fn (OrderDocument $doc): array => [
                'id' => $doc->id,
                'label' => $doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type)),
                'order_url' => route('orders.edit', (int) $doc->order_id).'?tab=documents',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OrderDocument>  $documents
     * @return list<array{id: int, label: string, order_url: string}>
     */
    private function serializeOtherDocs($documents): array
    {
        $structuredTypes = ['invoice', 'upd', 'act', 'invoice_factura', 'waybill', 'cmr', 'packing_list', 'customs_declaration'];
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
            ->map(fn (OrderDocument $doc): array => [
                'id' => $doc->id,
                'label' => $doc->number ?: ($doc->original_name ?: strtoupper((string) $doc->type)),
                'order_url' => route('orders.edit', (int) $doc->order_id).'?tab=documents',
            ])
            ->values()
            ->all();
    }

    private function ensureCanManageOrder(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        abort_unless($user->isManager() && (int) $order->manager_id === (int) $user->id, 403);
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
