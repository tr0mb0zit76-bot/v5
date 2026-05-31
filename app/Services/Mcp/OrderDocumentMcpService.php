<?php

namespace App\Services\Mcp;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;

class OrderDocumentMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
    ) {}

    /**
     * @return array{order_id: int, order_number: string|null, documents: list<array<string, mixed>>, total: int}
     */
    public function listForOrder(User $user, int $orderId): array
    {
        $this->access->requireDocumentsArea($user);

        $order = Order::query()->findOrFail($orderId);

        if (! $this->access->canAccessOrderDocuments($user, $order)) {
            throw new AuthenticationException('Нет доступа к документам этого заказа.');
        }

        $documents = OrderDocument::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->get();

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'documents' => $documents->map(fn (OrderDocument $document): array => $this->serialize($document))->all(),
            'total' => $documents->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OrderDocument $document): array
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $epd = is_array($metadata['epd'] ?? null) ? $metadata['epd'] : [];

        $payload = [
            'id' => $document->id,
            'type' => $document->type,
            'number' => $document->number,
            'document_date' => $document->document_date,
            'status' => $document->status,
            'party' => $metadata['party'] ?? null,
            'original_name' => $document->original_name,
            'source' => Schema::hasColumn('order_documents', 'source') ? $document->source : null,
            'workflow_status' => Schema::hasColumn('order_documents', 'workflow_status') ? $document->workflow_status : null,
            'has_file' => filled($document->file_path) || filled($document->generated_pdf_path),
        ];

        if ($document->type === 'etrn') {
            $payload['etrn_external_id'] = $epd['external_id'] ?? null;
            $payload['etrn_gis_status'] = $epd['gis_status'] ?? null;
        }

        return $payload;
    }
}
