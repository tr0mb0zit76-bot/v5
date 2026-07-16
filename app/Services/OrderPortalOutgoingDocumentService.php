<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderPortalInvite;
use App\Support\OrderDocumentDirection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderPortalOutgoingDocumentService
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
        private readonly OrderDocumentRequirementService $requirementService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForInvite(OrderPortalInvite $invite, string $party, string $downloadRouteName, string $token): array
    {
        $order = $invite->relationLoaded('order')
            ? $invite->order
            : Order::query()->with('documents')->findOrFail($invite->order_id);

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : $order->documents()->get();

        $typeLabels = collect($this->requirementService->documentTypeOptions())->pluck('label', 'value');

        return $documents
            ->filter(function (OrderDocument $document) use ($party): bool {
                if (! OrderDocumentDirection::isOutgoing($document)) {
                    return false;
                }

                if ((string) data_get($document->metadata, 'party', 'internal') !== $party) {
                    return false;
                }

                return filled($document->file_path);
            })
            ->sortBy('id')
            ->values()
            ->map(function (OrderDocument $document) use ($typeLabels, $downloadRouteName, $token): array {
                return [
                    'id' => $document->id,
                    'type' => $document->type,
                    'type_label' => (string) ($typeLabels->get($document->type) ?? $document->type),
                    'original_name' => $document->original_name,
                    'number' => $document->number,
                    'document_date' => optional($document->document_date)?->toDateString(),
                    'status' => $document->status,
                    'download_url' => route($downloadRouteName, [
                        'token' => $token,
                        'orderDocument' => $document->id,
                    ]),
                ];
            })
            ->all();
    }

    public function downloadForInvite(
        OrderPortalInvite $invite,
        OrderDocument $document,
        string $expectedParty,
    ): StreamedResponse {
        abort_unless((int) $document->order_id === (int) $invite->order_id, 404);
        abort_unless(OrderDocumentDirection::isOutgoing($document), 404);
        abort_unless((string) data_get($document->metadata, 'party', 'internal') === $expectedParty, 404);
        abort_unless(filled($document->file_path), 404);

        $driver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
        $contents = $this->documentStorage->get((string) $document->file_path, $driver);
        $name = (string) ($document->original_name ?: ('document-'.$document->id));
        $mime = trim((string) ($document->mime_type ?? '')) ?: 'application/octet-stream';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $name,
            [
                'Content-Type' => $mime,
            ],
        );
    }
}
