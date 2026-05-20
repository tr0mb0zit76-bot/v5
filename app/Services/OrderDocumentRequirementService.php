<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Support\OrderDocumentRequirementSlotBuilder;
use App\Support\OrderDocumentWorkflowStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
        return $this->requirementRulesForContext([], 'single_request');
    }

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>  $performers
     * @return list<array<string, mixed>>
     */
    public function requirementRulesForContext(array $performers, string $clientRequestMode = 'single_request'): array
    {
        return OrderDocumentRequirementSlotBuilder::buildRules($performers, $clientRequestMode);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirementRulesForOrder(Order $order): array
    {
        return $this->requirementRulesForContext(
            $this->resolvePerformersForOrder($order),
            $this->resolveClientRequestModeForOrder($order),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypeOptions(): array
    {
        return [
            ['value' => 'contract_request', 'label' => 'Договор-заявка'],
            ['value' => 'contract', 'label' => 'Договор'],
            ['value' => 'request', 'label' => 'Заявка'],
            ['value' => 'waybill', 'label' => 'ТН'],
            ['value' => 'etrn', 'label' => 'ЭТрН'],
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
        if ($order === null) {
            return $this->checklistForDocuments(collect(), $this->requirementRules());
        }

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : $order->documents()->get();

        return $this->checklistForDocuments($documents, $this->requirementRulesForOrder($order));
    }

    public function paymentPackageAttachedAt(Order $order, string $party): ?CarbonInterface
    {
        if (! in_array($party, ['customer', 'carrier'], true)) {
            return null;
        }

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : $order->documents()->get();

        $transportDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, ['waybill', 'cmr', 'etrn']));
        if ($transportDocuments->isEmpty()) {
            return null;
        }

        $requestDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, ['request', 'contract_request']) && $this->resolvePartyForMatching($document) === $party);
        $updDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, ['upd']) && $this->resolvePartyForMatching($document) === $party);
        $actDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, ['act']) && $this->resolvePartyForMatching($document) === $party);
        $invoiceFacturaDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, ['invoice_factura']) && $this->resolvePartyForMatching($document) === $party);

        $candidateDates = collect();
        $transportAt = $this->latestDocumentDate($transportDocuments);

        if ($transportAt === null) {
            return null;
        }

        $requestAt = $this->latestDocumentDate($requestDocuments);
        if ($requestAt !== null) {
            $candidateDates->push($transportAt->greaterThan($requestAt) ? $transportAt : $requestAt);
        }

        $updAt = $this->latestDocumentDate($updDocuments);
        if ($updAt !== null) {
            $candidateDates->push($transportAt->greaterThan($updAt) ? $transportAt : $updAt);
        }

        $actAt = $this->latestDocumentDate($actDocuments);
        $invoiceFacturaAt = $this->latestDocumentDate($invoiceFacturaDocuments);
        if ($actAt !== null && $invoiceFacturaAt !== null) {
            $closingAt = $actAt->greaterThan($invoiceFacturaAt) ? $actAt : $invoiceFacturaAt;
            $candidateDates->push($transportAt->greaterThan($closingAt) ? $transportAt : $closingAt);
        }

        return $candidateDates
            ->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();
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
    /**
     * @param  list<array<string, mixed>>|null  $rules
     */
    public function checklistForDocuments(iterable $documents, ?array $rules = null): array
    {
        $documentCollection = collect($documents);
        $ruleList = $rules ?? $this->requirementRules();
        $usedDocumentIds = [];

        return array_map(function (array $rule) use ($documentCollection, &$usedDocumentIds): array {
            $matchedDocument = $documentCollection->first(
                function (OrderDocument|array $document) use ($rule, &$usedDocumentIds): bool {
                    $documentId = $document instanceof OrderDocument
                        ? $document->getKey()
                        : (int) ($document['id'] ?? 0);

                    if ($documentId > 0 && in_array($documentId, $usedDocumentIds, true)) {
                        return false;
                    }

                    if (! $this->matchesRule($document, $rule)) {
                        return false;
                    }

                    return $this->requirementFulfilled($document);
                }
            );

            if ($matchedDocument !== null && ! ($rule['allows_multiple'] ?? false)) {
                $matchedId = $matchedDocument instanceof OrderDocument
                    ? $matchedDocument->getKey()
                    : (int) ($matchedDocument['id'] ?? 0);
                if ($matchedId > 0) {
                    $usedDocumentIds[] = $matchedId;
                }
            }

            return [
                ...$rule,
                'completed' => $matchedDocument !== null,
                'matched_document_id' => $matchedDocument instanceof OrderDocument
                    ? $matchedDocument->getKey()
                    : (is_array($matchedDocument) ? (int) ($matchedDocument['id'] ?? 0) ?: null : null),
            ];
        }, $ruleList);
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
     * @param  array<string, mixed>  $rule
     */
    private function matchesRule(OrderDocument|array $document, array $rule): bool
    {
        $type = $document instanceof OrderDocument
            ? (string) $document->type
            : (string) ($document['type'] ?? '');

        $party = $document instanceof OrderDocument
            ? $this->resolvePartyForMatching($document)
            : (string) data_get($document, 'party', data_get($document, 'metadata.party', 'internal'));

        if (! in_array($type, $rule['accepted_types'], true) || $party !== $rule['party']) {
            return false;
        }

        $ruleStage = filled($rule['order_leg_stage'] ?? null)
            ? $this->normalizeStageKey((string) $rule['order_leg_stage'])
            : null;
        $docStage = $this->documentLegStageKey($document);

        $ruleContractorId = isset($rule['contractor_id']) && (int) $rule['contractor_id'] > 0
            ? (int) $rule['contractor_id']
            : null;

        if ($ruleStage !== null) {
            if ($docStage !== $ruleStage) {
                return false;
            }
        } elseif ($docStage !== null && ($rule['slot_kind'] ?? '') !== 'waybill') {
            $slotKey = (string) ($rule['slot_key'] ?? '');
            $aggregatedCustomer = $party === 'customer' && $slotKey === 'customer-all';
            $aggregatedCarrier = $party === 'carrier' && $ruleContractorId !== null;

            if ($aggregatedCustomer || ! $aggregatedCarrier) {
                return false;
            }
        }

        $docContractorId = $this->documentCarrierContractorId($document);

        if ($ruleContractorId !== null) {
            if ($docContractorId !== $ruleContractorId) {
                return false;
            }
        } elseif ($ruleStage !== null && $party === 'carrier') {
            if ($docStage !== $ruleStage) {
                return false;
            }
        } elseif ($docContractorId !== null && $party === 'carrier') {
            return false;
        }

        $ruleSlotKey = filled($rule['slot_key'] ?? null) ? (string) $rule['slot_key'] : null;
        $docSlotKey = $this->documentRequirementSlotKey($document);

        if ($ruleSlotKey !== null && $docSlotKey !== null && $docSlotKey !== $ruleSlotKey) {
            return false;
        }

        return true;
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     */
    private function documentLegStageKey(OrderDocument|array $document): ?string
    {
        $raw = $document instanceof OrderDocument
            ? (data_get($document->metadata, 'order_leg_stage') ?? data_get($document->metadata, 'stage'))
            : (data_get($document, 'order_leg_stage')
                ?? data_get($document, 'metadata.order_leg_stage')
                ?? data_get($document, 'stage')
                ?? data_get($document, 'metadata.stage'));

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $this->normalizeStageKey($raw);
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     */
    private function documentCarrierContractorId(OrderDocument|array $document): ?int
    {
        $raw = $document instanceof OrderDocument
            ? data_get($document->metadata, 'carrier_contractor_id')
            : data_get($document, 'carrier_contractor_id', data_get($document, 'metadata.carrier_contractor_id'));

        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     */
    private function documentRequirementSlotKey(OrderDocument|array $document): ?string
    {
        $raw = $document instanceof OrderDocument
            ? data_get($document->metadata, 'requirement_slot_key')
            : data_get($document, 'requirement_slot_key', data_get($document, 'metadata.requirement_slot_key'));

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return trim($raw);
    }

    private function normalizeStageKey(string $stage): string
    {
        if (preg_match('/^Плечо (\d+)$/u', $stage, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^leg_\d+$/', $stage) === 1) {
            return $stage;
        }

        return $stage !== '' ? $stage : 'leg_1';
    }

    /**
     * @return list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null}>
     */
    private function resolvePerformersForOrder(Order $order): array
    {
        $saved = is_array($order->performers) ? $order->performers : [];
        if ($saved !== []) {
            return collect($saved)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'stage' => $row['stage'] ?? 'leg_1',
                    'contractor_id' => isset($row['contractor_id']) && $row['contractor_id'] !== null
                        ? (int) $row['contractor_id']
                        : null,
                    'contractor_name' => isset($row['contractor_name']) ? (string) $row['contractor_name'] : null,
                ])
                ->values()
                ->all();
        }

        if (! Schema::hasTable('order_legs')) {
            if ($order->carrier_id !== null) {
                return [['stage' => 'leg_1', 'contractor_id' => (int) $order->carrier_id, 'contractor_name' => null]];
            }

            return [];
        }

        $order->loadMissing(['legs.contractorAssignment']);

        return $order->legs
            ->sortBy('sequence')
            ->values()
            ->map(fn ($leg): array => [
                'stage' => (string) ($leg->description ?? 'leg_1'),
                'contractor_id' => $leg->contractorAssignment?->contractor_id !== null
                    ? (int) $leg->contractorAssignment->contractor_id
                    : ($order->carrier_id !== null ? (int) $order->carrier_id : null),
                'contractor_name' => null,
            ])
            ->all();
    }

    private function resolveClientRequestModeForOrder(Order $order): string
    {
        if (! Schema::hasTable('financial_terms')) {
            return 'single_request';
        }

        $order->loadMissing('financialTerms');
        $financialTerm = $order->financialTerms->first();
        if (! $financialTerm instanceof FinancialTerm) {
            return 'single_request';
        }

        $snapshot = $financialTerm->payment_terms_snapshot;
        if (! is_array($snapshot)) {
            return 'single_request';
        }

        $mode = data_get($snapshot, 'client.request_mode');

        return $mode === 'split_by_leg' ? 'split_by_leg' : 'single_request';
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

    /**
     * @param  Collection<int, OrderDocument>  $documents
     */
    private function latestDocumentDate($documents): ?CarbonInterface
    {
        $timestamps = $documents
            ->map(function (OrderDocument $document): ?CarbonInterface {
                if ($document->created_at instanceof CarbonInterface) {
                    return $document->created_at;
                }

                if ($document->updated_at instanceof CarbonInterface) {
                    return $document->updated_at;
                }

                return null;
            })
            ->filter();

        if ($timestamps->isEmpty()) {
            return null;
        }

        return $timestamps->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())->first();
    }

    /**
     * @param  list<string>  $acceptedTypes
     */
    private function matchesType(OrderDocument $document, array $acceptedTypes): bool
    {
        return in_array((string) $document->type, $acceptedTypes, true);
    }
}
