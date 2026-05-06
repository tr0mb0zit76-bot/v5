<?php

namespace App\Services\Epd;

use App\Models\OrderDocument;

class AstralEpdInboundService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): ?OrderDocument
    {
        $crmDocumentId = data_get($payload, 'document.crm_document_id');
        $externalId = data_get($payload, 'document.external_id');
        $eventType = (string) ($payload['event_type'] ?? 'unknown');
        $status = (string) data_get($payload, 'document.status', 'pending');

        $document = null;
        if (is_numeric($crmDocumentId)) {
            $document = OrderDocument::query()
                ->whereKey((int) $crmDocumentId)
                ->where('type', 'etrn')
                ->first();
        }

        if ($document === null && is_string($externalId) && $externalId !== '') {
            $document = OrderDocument::query()
                ->where('type', 'etrn')
                ->whereJsonContains('metadata->epd->external_id', $externalId)
                ->first();
        }

        if ($document === null) {
            return null;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $epd = is_array($metadata['epd'] ?? null) ? $metadata['epd'] : [];

        $epd['external_id'] = is_string($externalId) && $externalId !== ''
            ? $externalId
            : ($epd['external_id'] ?? null);
        $epd['last_event_type'] = $eventType;
        $epd['gis_status'] = $status;
        $epd['last_event_at'] = now()->toIso8601String();

        $metadata['epd'] = $epd;

        $document->forceFill([
            'status' => $this->mapStatus($status),
            'metadata' => $metadata,
        ])->save();

        return $document;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'draft',
            'signed', 'done', 'completed' => 'signed',
            default => 'sent',
        };
    }
}
