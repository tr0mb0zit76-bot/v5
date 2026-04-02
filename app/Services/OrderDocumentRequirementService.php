<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Support\Collection;

class OrderDocumentRequirementService
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>
     * }>
     */
    public function requirementRules(): array
    {
        return [
            [
                'key' => 'customer_request',
                'label' => 'Заявка заказчика',
                'description' => 'Договорная заявка или подтверждение заказа со стороны заказчика.',
                'party' => 'customer',
                'accepted_types' => ['request'],
            ],
            [
                'key' => 'carrier_request',
                'label' => 'Заявка перевозчику',
                'description' => 'Заявка или подтверждение со стороны перевозчика.',
                'party' => 'carrier',
                'accepted_types' => ['request'],
            ],
            [
                'key' => 'waybill',
                'label' => 'ТН',
                'description' => 'Транспортная накладная или эквивалентный перевозочный документ.',
                'party' => 'internal',
                'accepted_types' => ['waybill', 'cmr'],
            ],
            [
                'key' => 'customer_closing_document',
                'label' => 'Закрывающий документ заказчику',
                'description' => 'УПД, счет-фактура или акт для заказчика.',
                'party' => 'customer',
                'accepted_types' => ['upd', 'invoice_factura', 'act'],
            ],
            [
                'key' => 'carrier_closing_document',
                'label' => 'Закрывающий документ перевозчика',
                'description' => 'УПД, счет-фактура или акт от перевозчика.',
                'party' => 'carrier',
                'accepted_types' => ['upd', 'invoice_factura', 'act'],
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypeOptions(): array
    {
        return [
            ['value' => 'request', 'label' => 'Заявка'],
            ['value' => 'waybill', 'label' => 'ТН'],
            ['value' => 'cmr', 'label' => 'CMR'],
            ['value' => 'upd', 'label' => 'УПД'],
            ['value' => 'invoice', 'label' => 'Счет'],
            ['value' => 'invoice_factura', 'label' => 'Счет-фактура'],
            ['value' => 'act', 'label' => 'Акт'],
            ['value' => 'packing_list', 'label' => 'Пакинг-лист'],
            ['value' => 'customs_declaration', 'label' => 'Таможенная декларация'],
            ['value' => 'other', 'label' => 'Другое'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function partyOptions(): array
    {
        return [
            ['value' => 'customer', 'label' => 'Заказчик'],
            ['value' => 'carrier', 'label' => 'Перевозчик'],
            ['value' => 'internal', 'label' => 'Внутренний'],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>,
     *     completed: bool,
     *     matched_document_id: int|null
     * }>
     */
    public function checklistForOrder(?Order $order): array
    {
        return $this->checklistForDocuments($order?->documents ?? collect());
    }

    /**
     * @param  iterable<OrderDocument|array<string, mixed>>  $documents
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>,
     *     completed: bool,
     *     matched_document_id: int|null
     * }>
     */
    public function checklistForDocuments(iterable $documents): array
    {
        $documentCollection = collect($documents);

        return array_map(function (array $rule) use ($documentCollection): array {
            $matchedDocument = $documentCollection->first(
                fn (OrderDocument|array $document): bool => $this->matchesRule($document, $rule)
            );

            return [
                ...$rule,
                'completed' => $matchedDocument !== null,
                'matched_document_id' => $matchedDocument instanceof OrderDocument
                    ? $matchedDocument->getKey()
                    : (is_array($matchedDocument) ? (int) ($matchedDocument['id'] ?? 0) ?: null : null),
            ];
        }, $this->requirementRules());
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     * @param  array{party: string, accepted_types: list<string>}  $rule
     */
    private function matchesRule(OrderDocument|array $document, array $rule): bool
    {
        $type = $document instanceof OrderDocument
            ? (string) $document->type
            : (string) ($document['type'] ?? '');

        $party = $document instanceof OrderDocument
            ? (string) data_get($document->metadata, 'party', 'internal')
            : (string) data_get($document, 'party', data_get($document, 'metadata.party', 'internal'));

        return in_array($type, $rule['accepted_types'], true)
            && $party === $rule['party'];
    }
}
