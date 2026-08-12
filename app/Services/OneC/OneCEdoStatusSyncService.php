<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Services\OrderDocumentEdoAcknowledgementService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Подтягивает статус исходящего ЭДО (заказчику) из 1С в order_document_edo_acknowledgements.
 *
 * Цепочка: реализация → ОбъектыУчетаДокументовЭДО → ЭлектронныйДокументИсходящийЭДО (+ состояние).
 */
final class OneCEdoStatusSyncService
{
    public function __construct(
        private readonly OneCBpClient $oneC,
        private readonly OneCPublicationCatalog $publications,
        private readonly OrderDocumentEdoAcknowledgementService $edoAcknowledgements,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     updated: int,
     *     skipped_no_edo: int,
     *     skipped_unchanged: int,
     *     skipped_manual: int,
     *     errors: int
     * }
     */
    public function sync(?int $limit = null): array
    {
        $stats = [
            'checked' => 0,
            'updated' => 0,
            'skipped_no_edo' => 0,
            'skipped_unchanged' => 0,
            'skipped_manual' => 0,
            'errors' => 0,
        ];

        if (! (bool) config('one_c.enabled')) {
            return $stats;
        }

        if (! Schema::hasTable('order_one_c_documents') || ! Schema::hasTable('order_document_edo_acknowledgements')) {
            return $stats;
        }

        $query = OrderOneCDocument::query()
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->where('status', OrderOneCDocument::STATUS_CREATED)
            ->whereNotNull('external_ref')
            ->where('external_ref', '!=', '')
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        /** @var OrderOneCDocument $document */
        foreach ($query->cursor() as $document) {
            $stats['checked']++;

            try {
                $result = $this->syncDocument($document);
                $stats[$result]++;
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('one_c.edo_sync_failed', [
                    'order_one_c_document_id' => $document->id,
                    'order_id' => $document->order_id,
                    'external_ref' => $document->external_ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return 'updated'|'skipped_no_edo'|'skipped_unchanged'|'skipped_manual'
     */
    private function syncDocument(OrderOneCDocument $document): string
    {
        $order = Order::query()->find((int) $document->order_id);
        if ($order === null) {
            return 'skipped_no_edo';
        }

        $baseUrl = $this->resolveBaseUrl($document, $order);
        $realization = $this->oneC->getRealization((string) $document->external_ref, $baseUrl);
        if ($realization === null) {
            return 'skipped_no_edo';
        }

        $raw = is_array($realization['raw'] ?? null) ? $realization['raw'] : [];
        $isUpd = (bool) ($raw['ЭтоУниверсальныйДокумент'] ?? false);
        $counterpartyRef = trim((string) ($raw['Контрагент_Key'] ?? ''));

        $sent = $this->resolveSentOutgoingEdo(
            realizationRef: (string) $document->external_ref,
            counterpartyRef: $counterpartyRef,
            baseUrl: $baseUrl,
            fallbackNumber: (string) ($realization['number'] ?? $document->external_number ?? ''),
        );

        if ($sent === null) {
            return 'skipped_no_edo';
        }

        $types = $this->closingTypesForEdo($sent['regulation_type'], $isUpd);
        $documentDate = $this->toDateString($sent['sent_at'] ?? $sent['state_changed_at'] ?? null);
        $anyChanged = false;
        $anyManual = false;

        foreach ($types as $documentType) {
            $result = $this->edoAcknowledgements->upsertFromOneC($order, [
                'party' => 'customer',
                'document_type' => $documentType,
                'slot_key' => '',
                'contractor_id' => 0,
                'document_number' => $sent['number'],
                'document_date' => $documentDate,
            ]);

            if ($result['skipped_manual']) {
                $anyManual = true;
            }
            if ($result['changed']) {
                $anyChanged = true;
            }
        }

        $payload = is_array($document->response_payload) ? $document->response_payload : [];
        $payload['edo_sync'] = [
            'edo_ref' => $sent['edo_ref'],
            'state' => $sent['state'],
            'number' => $sent['number'],
            'sent_at' => $sent['sent_at'],
            'regulation_type' => $sent['regulation_type'],
            'synced_at' => now()->toIso8601String(),
        ];
        $document->response_payload = $payload;
        $document->save();

        if ($anyChanged) {
            return 'updated';
        }

        return $anyManual ? 'skipped_manual' : 'skipped_unchanged';
    }

    /**
     * @return array{
     *     edo_ref: string,
     *     number: string,
     *     sent_at: ?string,
     *     state: ?string,
     *     state_changed_at: ?string,
     *     regulation_type: ?string
     * }|null
     */
    private function resolveSentOutgoingEdo(
        string $realizationRef,
        string $counterpartyRef,
        ?string $baseUrl,
        string $fallbackNumber,
    ): ?array {
        $objectRefs = [$realizationRef];

        foreach ($this->oneC->findIssuedInvoiceFacturasForRealization($realizationRef, $counterpartyRef, $baseUrl) as $sf) {
            if ($sf['ref'] !== '') {
                $objectRefs[] = $sf['ref'];
            }
        }

        $sentStates = array_map('strval', config('one_c.edo_sync.sent_states', [
            'ОбменЗавершен',
            'ОжидаетсяПодтверждение',
            'ОжидаетсяПодтверждениеОператора',
        ]));

        foreach (array_unique($objectRefs) as $objectRef) {
            foreach ($this->oneC->findEdoLinksForAccountingObject($objectRef, $baseUrl) as $link) {
                if (! $link['actual']) {
                    continue;
                }
                if (! str_contains($link['edo_type'], 'Исходящий')) {
                    continue;
                }

                $edo = $this->oneC->getOutgoingEdoDocument($link['edo_ref'], $baseUrl);
                if ($edo === null) {
                    continue;
                }

                $state = $this->oneC->getEdoDocumentState($link['edo_ref'], $baseUrl);
                $stateName = $state['state'] ?? null;
                $sentByDate = $edo['sent_at'] !== null;
                $sentByState = $stateName !== null && in_array($stateName, $sentStates, true);

                if (! $sentByDate && ! $sentByState) {
                    continue;
                }

                $number = trim((string) ($edo['number'] ?? ''));
                if ($number === '') {
                    $number = trim($fallbackNumber);
                }
                if ($number === '') {
                    continue;
                }

                return [
                    'edo_ref' => $link['edo_ref'],
                    'number' => $number,
                    'sent_at' => $edo['sent_at'],
                    'state' => $stateName,
                    'state_changed_at' => $state['changed_at'] ?? null,
                    'regulation_type' => $edo['regulation_type'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function closingTypesForEdo(?string $regulationType, bool $isUniversalDocument): array
    {
        $regulation = mb_strtoupper(trim((string) $regulationType));
        if ($isUniversalDocument || str_contains($regulation, 'УПД')) {
            return ['upd'];
        }

        return ['invoice_factura', 'act'];
    }

    private function resolveBaseUrl(OrderOneCDocument $document, Order $order): ?string
    {
        $request = is_array($document->request_payload) ? $document->request_payload : [];
        $base = trim((string) ($request['base_url'] ?? ''));
        if ($base !== '') {
            return rtrim($base, '/');
        }

        $code = trim((string) ($request['publication_code'] ?? ''));
        if ($code !== '') {
            try {
                $pub = $this->publications->get($code);
                if ($pub['base_url'] !== '') {
                    return rtrim($pub['base_url'], '/');
                }
            } catch (Throwable) {
                // fall through to forOrder
            }
        }

        $pub = $this->publications->forOrder($order);

        return $pub['base_url'] !== '' ? rtrim($pub['base_url'], '/') : null;
    }

    private function toDateString(?string $dateTime): ?string
    {
        if ($dateTime === null || $dateTime === '') {
            return null;
        }

        return substr($dateTime, 0, 10);
    }
}
