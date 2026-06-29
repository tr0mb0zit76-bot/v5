<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEtrnFromOrderRequest;
use App\Http\Requests\OneCFreshStatusPushRequest;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\Epd\EtrnDraftBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OneCFreshEtrnController extends Controller
{
    public function createFromOrder(CreateEtrnFromOrderRequest $request, EtrnDraftBuilder $builder): JsonResponse
    {
        $payload = $request->validated();
        $order = Order::query()->findOrFail((int) $payload['order_id']);
        $order = $this->loadOrderForEtrnDraft($order);

        $draft = $builder->build($order);
        $missingRequiredFields = $draft['missing_required_fields'];
        $allowIncomplete = (bool) ($payload['allow_incomplete'] ?? false);

        if ($missingRequiredFields !== [] && ! $allowIncomplete) {
            throw ValidationException::withMessages([
                'order' => ['Недостаточно данных для формирования ЭТрН-пакета.'],
                'missing_required_fields' => $missingRequiredFields,
            ]);
        }

        $document = $this->createEtrnDocument($order, $draft['payload'], $missingRequiredFields);

        return response()->json([
            'ok' => true,
            'document_id' => $document->id,
            'status' => $document->status,
            'missing_required_fields' => $missingRequiredFields,
        ]);
    }

    public function index(Order $order): JsonResponse
    {
        $documents = OrderDocument::query()
            ->where('order_id', $order->id)
            ->where('type', 'etrn')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderDocument $document): array => $this->serializeEtrnDocument($document))
            ->values();

        return response()->json(['documents' => $documents]);
    }

    public function latestDraft(Order $order): JsonResponse
    {
        $document = OrderDocument::query()
            ->where('order_id', $order->id)
            ->where('type', 'etrn')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'document' => $document ? $this->serializeEtrnDocument($document) : null,
        ]);
    }

    public function journal(): JsonResponse
    {
        $documents = OrderDocument::query()
            ->where('type', 'etrn')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (OrderDocument $document): array => $this->serializeEtrnDocument($document))
            ->values();

        return response()->json([
            'journal' => $documents,
        ]);
    }

    public function pushStatus(OneCFreshStatusPushRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $document = OrderDocument::query()
            ->whereKey((int) $payload['crm_document_id'])
            ->where('type', 'etrn')
            ->firstOrFail();

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $epd = is_array($metadata['epd'] ?? null) ? $metadata['epd'] : [];
        $epd['external_id'] = $payload['external_id'] ?? ($epd['external_id'] ?? null);
        $epd['gis_status'] = $payload['status'];
        $epd['last_sync_source'] = '1c_fresh';
        $epd['last_sync_at'] = now()->toIso8601String();
        $metadata['epd'] = $epd;

        $document->forceFill([
            'status' => (string) $payload['status'],
            'metadata' => $metadata,
        ])->save();

        return response()->json([
            'ok' => true,
            'document_id' => $document->id,
        ]);
    }

    private function loadOrderForEtrnDraft(Order $order): Order
    {
        $order->load([
            'client',
            'carrier',
            'routePoints',
            'cargoItems',
        ]);

        return $order;
    }

    /**
     * @param  list<string>  $missingRequiredFields
     * @param  array<string, mixed>  $draftPayload
     */
    private function createEtrnDocument(Order $order, array $draftPayload, array $missingRequiredFields): OrderDocument
    {
        $metadata = [
            'party' => 'internal',
            'flow' => 'one_c_fresh_draft',
            'epd' => [
                'gis_status' => $missingRequiredFields === [] ? 'ready_for_1c' : 'draft_incomplete',
                'last_sync_source' => 'crm',
                'last_sync_at' => now()->toIso8601String(),
            ],
            'etrn_draft' => $draftPayload,
            'etrn_missing_required_fields' => $missingRequiredFields,
        ];

        $attributes = [
            'order_id' => $order->id,
            'type' => 'etrn',
            'status' => $missingRequiredFields === [] ? 'pending' : 'draft',
            'number' => $order->waybill_number ?: null,
            'document_date' => optional($order->loading_date)?->toDateString(),
            'metadata' => $metadata,
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ];

        return OrderDocument::query()->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEtrnDocument(OrderDocument $document): array
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $epd = is_array($metadata['epd'] ?? null) ? $metadata['epd'] : [];

        return [
            'id' => $document->id,
            'order_id' => $document->order_id,
            'type' => $document->type,
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
            'status' => $document->status,
            'external_id' => $epd['external_id'] ?? null,
            'gis_status' => $epd['gis_status'] ?? null,
            'updated_at' => optional($document->updated_at)?->toIso8601String(),
            'missing_required_fields' => data_get($metadata, 'etrn_missing_required_fields', []),
        ];
    }
}
