<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Support\Facades\Schema;

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
                'description' => 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.',
                'party' => 'customer',
                'accepted_types' => ['request', 'contract_request'],
            ],
            [
                'key' => 'carrier_request',
                'label' => 'Заявка перевозчику',
                'description' => 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.',
                'party' => 'carrier',
                'accepted_types' => ['request', 'contract_request'],
            ],
            [
                'key' => 'waybill',
                'label' => 'ТН',
                'description' => 'Транспортная накладная: статус «Отправлен» или «Подписан».',
                'party' => 'internal',
                'accepted_types' => ['waybill', 'cmr'],
            ],
            [
                'key' => 'customer_closing_document',
                'label' => 'Закрывающий документ заказчику',
                'description' => 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».',
                'party' => 'customer',
                'accepted_types' => ['upd', 'invoice_factura', 'act'],
            ],
            [
                'key' => 'carrier_closing_document',
                'label' => 'Закрывающий документ перевозчика',
                'description' => 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».',
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
            ['value' => 'contract_request', 'label' => 'Договор-заявка'],
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
                    && $this->requirementFulfilled($document)
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
     */
    private function requirementFulfilled(OrderDocument|array $document): bool
    {
        if ($document instanceof OrderDocument) {
            return $this->requirementFulfilledForModel($document);
        }

        $status = (string) ($document['status'] ?? '');

        return in_array($status, ['sent', 'signed'], true);
    }

    private function requirementFulfilledForModel(OrderDocument $document): bool
    {
        $isPrint = (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || (data_get($document->metadata, 'flow') === 'print_template_workflow');

        if ($isPrint && Schema::hasColumn('order_documents', 'workflow_status')) {
            if ($document->workflow_status !== OrderDocumentWorkflowStatus::FINALIZED) {
                return false;
            }

            if (Schema::hasColumn('order_documents', 'requires_counterparty_signature')
                && (bool) ($document->requires_counterparty_signature ?? false)) {
                return ($document->signature_status ?? '') === 'signed_both_sides';
            }

            return true;
        }

        $status = (string) ($document->status ?? '');

        return in_array($status, ['sent', 'signed'], true);
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
            ? $this->resolvePartyForMatching($document)
            : (string) data_get($document, 'party', data_get($document, 'metadata.party', 'internal'));

        return in_array($type, $rule['accepted_types'], true)
            && $party === $rule['party'];
    }

    private function resolvePartyForMatching(OrderDocument $document): string
    {
        $meta = is_array($document->metadata) ? $document->metadata : [];

        if (filled($meta['party'] ?? null)) {
            return (string) $meta['party'];
        }

        if ($document->template_id !== null) {
            $template = PrintFormTemplate::query()->find($document->template_id);
            if ($template !== null) {
                $p = $template->party ?? null;
                if (is_string($p) && $p !== '' && in_array($p, ['customer', 'carrier', 'internal'], true)) {
                    return $p;
                }
                if (in_array($template->document_type, ['request', 'contract_request'], true)) {
                    return 'customer';
                }
            }
        }

        if (($meta['flow'] ?? '') === 'print_template_workflow' && in_array($document->type, ['request', 'contract_request'], true)) {
            return 'customer';
        }

        return 'internal';
    }
}
