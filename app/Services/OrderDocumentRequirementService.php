<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Models\PrintFormTemplate;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderDocumentClosingFulfillment;
use App\Support\OrderDocumentRequirementSlotBuilder;
use App\Support\OrderDocumentTransportTypes;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\PaymentFormDictionary;
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
    public function requirementRulesForContext(
        array $performers,
        string $clientRequestMode = 'single_request',
        array $additionalCosts = [],
        array $paymentContext = [],
    ): array {
        return OrderDocumentRequirementSlotBuilder::buildRules($performers, $clientRequestMode, $additionalCosts, $paymentContext);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirementRulesForOrder(Order $order): array
    {
        $rules = $this->requirementRulesForContext(
            $this->resolvePerformersForOrder($order),
            $this->resolveClientRequestModeForOrder($order),
            $this->resolveAdditionalCostsForOrder($order),
            $this->resolvePaymentContextForOrder($order),
        );

        return $this->enrichRequirementRulesWithCounterpartyLabels($order, $rules);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function enrichRequirementRulesWithCounterpartyLabels(Order $order, array $rules): array
    {
        $customerName = $this->resolveCustomerDisplayName($order);

        return array_map(static function (array $rule) use ($customerName): array {
            if (($rule['party'] ?? '') === 'customer' && blank($rule['counterparty_label'] ?? null) && $customerName !== '') {
                $rule['counterparty_label'] = $customerName;
            }

            return $rule;
        }, $rules);
    }

    private function resolveCustomerDisplayName(Order $order): string
    {
        $order->loadMissing('client:id,name');

        return trim((string) ($order->client?->name ?? ''));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypeOptions(): array
    {
        $options = [
            ['value' => 'contract_request', 'label' => 'Договор-заявка'],
            ['value' => 'contract', 'label' => 'Договор'],
            ['value' => 'request', 'label' => 'Заявка'],
            ['value' => 'upd', 'label' => 'УПД'],
            ['value' => 'invoice', 'label' => 'Счет'],
            ['value' => 'invoice_factura', 'label' => 'Счет-фактура'],
            ['value' => 'act', 'label' => 'Акт'],
            ['value' => 'packing_list', 'label' => 'Пакинг-лист'],
            ['value' => 'customs_declaration', 'label' => 'Таможенная декларация'],
            ['value' => 'other', 'label' => 'Другое'],
        ];

        return array_merge($options, OrderDocumentTransportTypes::selectOptions());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function partyOptions(): array
    {
        return [
            ['value' => 'customer', 'label' => 'Заказчик'],
            ['value' => 'carrier', 'label' => 'Перевозчик'],
            ['value' => 'contractor', 'label' => 'Подрядчик'],
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

        $edoAcknowledgements = $this->edoAcknowledgementsForOrder($order);

        return $this->checklistForDocuments($documents, $this->requirementRulesForOrder($order), $edoAcknowledgements);
    }

    /**
     * @return Collection<int, OrderDocumentEdoAcknowledgement>
     */
    private function edoAcknowledgementsForOrder(Order $order): Collection
    {
        if ($order->relationLoaded('edoAcknowledgements')) {
            return $order->edoAcknowledgements;
        }

        return app(OrderDocumentEdoAcknowledgementService::class)->acknowledgementsForOrder($order);
    }

    public function paymentPackageAttachedAt(Order $order, string $party): ?CarbonInterface
    {
        if (! in_array($party, ['customer', 'carrier'], true)) {
            return null;
        }

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : $order->documents()->get();

        $transportDocuments = $documents->filter(fn (OrderDocument $document): bool => $this->matchesType($document, OrderDocumentTransportTypes::VALUES));
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

        $edoClosingAt = $this->latestEdoClosingDate($order, $party);
        if ($edoClosingAt !== null) {
            $candidateDates->push($transportAt->greaterThan($edoClosingAt) ? $transportAt : $edoClosingAt);
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
     * @param  iterable<OrderDocumentEdoAcknowledgement>|null  $edoAcknowledgements
     */
    public function checklistForDocuments(iterable $documents, ?array $rules = null, ?iterable $edoAcknowledgements = null): array
    {
        $documentCollection = collect($documents);
        $edoCollection = collect($edoAcknowledgements ?? []);
        $ruleList = $rules ?? $this->requirementRules();
        $usedDocumentIds = [];

        return array_map(function (array $rule) use ($documentCollection, $edoCollection, &$usedDocumentIds): array {
            if (OrderDocumentClosingFulfillment::isClosingSlotKind((string) ($rule['slot_kind'] ?? ''))) {
                $completed = OrderDocumentClosingFulfillment::isRuleFulfilled(
                    $rule,
                    $documentCollection,
                    $edoCollection,
                );

                return [
                    ...$rule,
                    'completed' => $completed,
                    'matched_document_id' => null,
                ];
            }

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
    public function documentsMatchingRule(OrderDocument|array $document, array $rule): bool
    {
        return $this->matchesRule($document, $rule);
    }

    /**
     * Слоты документов перевозчика для гостевой ссылки (заявка и закрывающие по плечу/исполнителю).
     *
     * @return list<array<string, mixed>>
     */
    public function rulesForCarrierPortalInvite(Order $order, int $contractorId, string $stage, int $carrierSlot): array
    {
        return $this->rulesForCarrierPortalInviteFromPerformers(
            $this->resolvePerformersForOrder($order),
            $this->resolveClientRequestModeForOrder($order),
            $contractorId,
            $stage,
            $carrierSlot,
        );
    }

    /**
     * Слоты документов заказчика для гостевой ссылки (заявка, закрывающие, пакинг/счёт).
     *
     * @return list<array<string, mixed>>
     */
    public function rulesForCustomerPortalInvite(Order $order): array
    {
        return collect($this->requirementRulesForOrder($order))
            ->filter(function (array $rule): bool {
                if (($rule['party'] ?? '') !== 'customer') {
                    return false;
                }

                $slotKind = (string) ($rule['slot_kind'] ?? '');

                return in_array($slotKind, ['customer_request', 'customer_closing'], true);
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{stage?: string|null, contractor_id?: int|null, contractor_name?: string|null, carrier_mode?: string|null, split_carriers?: list<array<string, mixed>>|null}>  $performers
     * @return list<array<string, mixed>>
     */
    public function rulesForCarrierPortalInviteFromPerformers(
        array $performers,
        string $clientRequestMode,
        int $contractorId,
        string $stage,
        int $carrierSlot,
    ): array {
        $normalizedStage = $this->normalizeStageKey($stage);
        $carrierSlot = max(1, min(4, $carrierSlot));

        return collect($this->requirementRulesForContext($performers, $clientRequestMode))
            ->filter(function (array $rule) use ($contractorId, $normalizedStage, $carrierSlot): bool {
                if (($rule['party'] ?? '') !== 'carrier') {
                    return false;
                }

                $slotKind = (string) ($rule['slot_kind'] ?? '');
                if (! in_array($slotKind, ['carrier_request', 'carrier_closing'], true)) {
                    return false;
                }

                if (($rule['slot_key'] ?? '') === 'carrier-empty') {
                    return false;
                }

                $ruleContractorId = isset($rule['contractor_id']) && (int) $rule['contractor_id'] > 0
                    ? (int) $rule['contractor_id']
                    : null;

                if ($ruleContractorId !== null && $ruleContractorId !== $contractorId) {
                    return false;
                }

                $ruleStage = filled($rule['order_leg_stage'] ?? null)
                    ? $this->normalizeStageKey((string) $rule['order_leg_stage'])
                    : null;

                if ($ruleStage !== null && $ruleStage !== $normalizedStage) {
                    return false;
                }

                $slotKey = (string) ($rule['slot_key'] ?? '');
                if (str_contains($slotKey, '-slot')) {
                    $expectedSuffix = '-slot'.$carrierSlot;

                    if (! str_ends_with($slotKey, $expectedSuffix)) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
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

        $isWaybillSlot = ($rule['slot_kind'] ?? '') === 'waybill';

        if (! in_array($type, $rule['accepted_types'], true)) {
            return false;
        }

        if ($isWaybillSlot) {
            if (! in_array($party, ['carrier', 'internal'], true)) {
                return false;
            }
        } elseif ($party !== $rule['party']) {
            return false;
        }

        if ($isWaybillSlot) {
            return true;
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
                    'carrier_mode' => isset($row['carrier_mode']) ? (string) $row['carrier_mode'] : 'single',
                    'execution_mode' => isset($row['execution_mode']) ? (string) $row['execution_mode'] : null,
                    'split_carriers' => is_array($row['split_carriers'] ?? null) ? $row['split_carriers'] : null,
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

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveAdditionalCostsForOrder(Order $order): array
    {
        if (! Schema::hasTable('financial_terms')) {
            return [];
        }

        $order->loadMissing('financialTerms');
        $financialTerm = $order->financialTerms->first();

        if (! $financialTerm instanceof FinancialTerm) {
            return [];
        }

        $rows = is_array($financialTerm->additional_costs) ? $financialTerm->additional_costs : [];

        return OrderAdditionalCostNormalizer::normalizeList(
            $rows,
            (string) ($financialTerm->client_currency ?? 'RUB'),
            optional($order->additional_expenses_payment_date)?->toDateString(),
        );
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

    /**
     * @return array{customer: string|null, carriers: array<int, string|null>}
     */
    private function resolvePaymentContextForOrder(Order $order): array
    {
        $customer = filled($order->customer_payment_form)
            ? PaymentFormDictionary::normalizeForStorage((string) $order->customer_payment_form)
            : null;

        /** @var array<int, string|null> $carriers */
        $carriers = [];

        if (Schema::hasTable('financial_terms')) {
            $order->loadMissing('financialTerms');
            $financialTerm = $order->financialTerms->first();

            if ($financialTerm instanceof FinancialTerm) {
                $costRows = is_array($financialTerm->contractors_costs) ? $financialTerm->contractors_costs : [];

                foreach ($costRows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $contractorId = isset($row['contractor_id']) && $row['contractor_id'] !== null && $row['contractor_id'] !== ''
                        ? (int) $row['contractor_id']
                        : 0;

                    if ($contractorId <= 0) {
                        continue;
                    }

                    $carriers[$contractorId] = PaymentFormDictionary::normalizeForStorage($row['payment_form'] ?? null);
                }
            }
        }

        if (Schema::hasTable('leg_costs') && Schema::hasTable('order_legs')) {
            $order->loadMissing(['legs.contractorAssignment', 'legs.cost']);

            foreach ($order->legs as $leg) {
                $contractorId = $leg->contractorAssignment?->contractor_id;

                if ($contractorId === null) {
                    continue;
                }

                $paymentForm = PaymentFormDictionary::normalizeForStorage($leg->cost?->payment_form);

                if ($paymentForm !== null) {
                    $carriers[(int) $contractorId] = $paymentForm;
                }
            }
        }

        return [
            'customer' => $customer,
            'carriers' => $carriers,
        ];
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

    private function latestEdoClosingDate(Order $order, string $party): ?CarbonInterface
    {
        $rules = collect($this->requirementRulesForOrder($order))
            ->filter(fn (array $rule): bool => ($rule['party'] ?? '') === $party
                && OrderDocumentClosingFulfillment::isClosingSlotKind((string) ($rule['slot_kind'] ?? '')));

        if ($rules->isEmpty()) {
            return null;
        }

        $documents = $order->relationLoaded('documents') ? $order->documents : $order->documents()->get();
        $edoAcknowledgements = $this->edoAcknowledgementsForOrder($order);

        $best = null;

        foreach ($rules as $rule) {
            $candidate = $this->closingPackageDateForRule($rule, $documents, $edoAcknowledgements);

            if ($candidate === null) {
                continue;
            }

            if ($best === null || $candidate->lessThan($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  Collection<int, OrderDocument>  $documents
     * @param  Collection<int, OrderDocumentEdoAcknowledgement>  $edoAcknowledgements
     */
    private function closingPackageDateForRule(array $rule, Collection $documents, Collection $edoAcknowledgements): ?CarbonInterface
    {
        if (OrderDocumentClosingFulfillment::hasTypeFulfilled('upd', $rule, $documents, $edoAcknowledgements)) {
            return $this->bestClosingTypeDate('upd', $rule, $documents, $edoAcknowledgements);
        }

        if (
            OrderDocumentClosingFulfillment::hasTypeFulfilled('act', $rule, $documents, $edoAcknowledgements)
            && OrderDocumentClosingFulfillment::hasTypeFulfilled('invoice_factura', $rule, $documents, $edoAcknowledgements)
        ) {
            $actDate = $this->bestClosingTypeDate('act', $rule, $documents, $edoAcknowledgements);
            $invoiceDate = $this->bestClosingTypeDate('invoice_factura', $rule, $documents, $edoAcknowledgements);

            if ($actDate === null || $invoiceDate === null) {
                return null;
            }

            return $actDate->greaterThan($invoiceDate) ? $actDate : $invoiceDate;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  Collection<int, OrderDocument>  $documents
     * @param  Collection<int, OrderDocumentEdoAcknowledgement>  $edoAcknowledgements
     */
    private function bestClosingTypeDate(
        string $documentType,
        array $rule,
        Collection $documents,
        Collection $edoAcknowledgements,
    ): ?CarbonInterface {
        $fileDate = $documents
            ->filter(fn (OrderDocument $document): bool => OrderDocumentClosingFulfillment::documentMatchesClosingType($document, $documentType, $rule)
                && OrderDocumentClosingFulfillment::documentFileFulfilled($document))
            ->map(fn (OrderDocument $document): ?CarbonInterface => $document->document_date ?? $document->created_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();

        if ($fileDate instanceof CarbonInterface) {
            return $fileDate;
        }

        return $edoAcknowledgements
            ->filter(fn (OrderDocumentEdoAcknowledgement $row): bool => OrderDocumentClosingFulfillment::acknowledgementMatchesClosingType($row, $documentType, $rule)
                && OrderDocumentClosingFulfillment::acknowledgementFulfilled($row))
            ->map(fn (OrderDocumentEdoAcknowledgement $row): ?CarbonInterface => $row->document_date ?? $row->confirmed_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();
    }

    /**
     * @param  list<string>  $acceptedTypes
     */
    private function matchesType(OrderDocument $document, array $acceptedTypes): bool
    {
        return in_array((string) $document->type, $acceptedTypes, true);
    }
}
