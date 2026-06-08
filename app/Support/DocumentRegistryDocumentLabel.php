<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Contractor;
use App\Models\OrderDocument;

/**
 * Подпись документа в реестре: номер/имя + исполнитель + перевозчик.
 */
final class DocumentRegistryDocumentLabel
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $contractorNamesById
     */
    public static function build(OrderDocument $document, array $metadata, array $contractorNamesById): string
    {
        $base = trim((string) ($document->number ?: ($document->original_name ?: 'Документ')));

        $parts = [$base !== '' ? $base : 'Документ'];

        $slot = isset($metadata['carrier_slot']) ? (int) $metadata['carrier_slot'] : 0;
        if ($slot > 0) {
            $parts[] = 'исп. '.$slot;
        }

        $contractorId = (int) ($metadata['carrier_contractor_id'] ?? $metadata['contractor_id'] ?? 0);
        if ($contractorId > 0) {
            $name = trim((string) ($contractorNamesById[$contractorId] ?? ''));
            if ($name !== '') {
                $parts[] = $name;
            }
        }

        return implode(' · ', array_values(array_unique($parts)));
    }

    /**
     * @param  iterable<int, OrderDocument>  $documents
     * @return array<int, string>
     */
    public static function contractorNamesByIdFromDocuments(iterable $documents): array
    {
        $ids = collect($documents)
            ->map(static function (OrderDocument $document): int {
                $meta = (array) ($document->metadata ?? []);

                return (int) ($meta['carrier_contractor_id'] ?? $meta['contractor_id'] ?? 0);
            })
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Contractor::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->all();
    }
}
