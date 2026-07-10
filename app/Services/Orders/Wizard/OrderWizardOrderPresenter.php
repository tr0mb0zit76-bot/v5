<?php

namespace App\Services\Orders\Wizard;

use App\Models\Cargo;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Services\PaymentSettlementSummaryBuilder;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Support\CargoPerformerAllocationNormalizer;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderCargoItemsPayloadNormalizer;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderFinancialEditAuthorization;
use App\Support\OrderLeadPrecalculationSnapshotResolver;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PerformerRouteActualDates;
use App\Support\RoutePointNormalizedData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use JsonException;

class OrderWizardOrderPresenter
{
    public function __construct(
        private readonly OrderWizardPerformersPayloadBuilder $performersPayloadBuilder,
        private readonly OrderWizardContractorsCostsNormalizer $contractorsCostsNormalizer,
        private readonly OrderWizardOrderDocumentSerializer $documentSerializer,
        private readonly OrderWizardOrderAuthorization $authorization,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        Request $request,
        Order $order,
        bool $canManageOrderDocuments,
        bool $canApproveOrderDocuments,
    ): array {
        if (Schema::hasTable('payment_schedules')) {
            PaymentScheduleAutomaticStatus::refreshForOrder((int) $order->id);
        }

        $financialTerm = Schema::hasTable('financial_terms') ? $order->financialTerms->first() : null;
        $wizardState = $this->resolveWizardState($order);
        $useWizardState = is_array($wizardState) && filled($wizardState['financial_term'] ?? null);
        /** @var array<string, mixed> $wizardFt */
        $wizardFt = $useWizardState ? ($wizardState['financial_term'] ?? []) : [];

        $paymentTermsRaw = null;
        if (! $useWizardState) {
            if (Schema::hasColumn('orders', 'payment_terms')) {
                $paymentTermsRaw = $order->getAttribute('payment_terms');
            }
            if (blank($paymentTermsRaw) && $financialTerm !== null && Schema::hasColumn('financial_terms', 'payment_terms_snapshot')) {
                $paymentTermsRaw = $financialTerm->payment_terms_snapshot;
            }
        }

        if ($useWizardState) {
            $paymentTermsConfig = [
                'client' => [
                    'request_mode' => $wizardFt['client_request_mode'] ?? 'single_request',
                    'payment_schedule' => $wizardFt['client_payment_schedule'] ?? [],
                ],
            ];
        } else {
            $paymentTermsConfig = $this->decodePaymentTermsConfig($paymentTermsRaw);
        }
        $routePointHasAddressColumn = Schema::hasColumn('route_points', 'address');
        $cargoItems = $this->orderCargoItems($order);
        $documents = Schema::hasTable('order_documents')
            ? $order->documents
                ->reject(fn (OrderDocument $document): bool => $this->documentSerializer->isEmptyPrintWorkflowArtifact($document))
                ->values()
            : collect();
        $templateIds = $documents->pluck('template_id')->filter()->unique()->values()->all();
        /** @var Collection<int, PrintFormTemplate> $templatesById */
        $templatesById = collect();
        if ($templateIds !== [] && Schema::hasTable('print_form_templates')) {
            $templatesById = PrintFormTemplate::query()->whereIn('id', $templateIds)->get()->keyBy('id');
        }
        $statusLogs = Schema::hasTable('order_status_logs') ? $order->statusLogs : collect();
        $routePoints = $order->legs
            ->sortBy('sequence')
            ->flatMap(function ($leg) use ($routePointHasAddressColumn) {
                return $leg->routePoints
                    ->sortBy('sequence')
                    ->map(fn ($point): array => [
                        'id' => $point->id,
                        'stage' => CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) $leg->description),
                        'leg_sequence' => $leg->sequence,
                        'type' => $point->type,
                        'sequence' => $point->sequence,
                        'address' => $routePointHasAddressColumn && filled($point->address)
                            ? $point->address
                            : data_get($point->metadata, 'address',
                                data_get($point->metadata, 'full_address',
                                    data_get($point->normalized_data, 'result', $point->instructions))),
                        'normalized_data' => RoutePointNormalizedData::resolveForWizard($point),
                        'planned_date' => optional($point->planned_date)?->toDateString(),
                        'planned_time_from' => filled($point->planned_time_from) ? substr((string) $point->planned_time_from, 0, 5) : null,
                        'planned_time_to' => filled($point->planned_time_to) ? substr((string) $point->planned_time_to, 0, 5) : null,
                        'actual_date' => optional($point->actual_date)?->toDateString(),
                        'actual_time' => filled($point->actual_time) ? substr((string) $point->actual_time, 0, 5) : null,
                        'contact_person' => $point->contact_person,
                        'contact_phone' => $point->contact_phone,
                        'sender_name' => $point->sender_name,
                        'sender_contact' => $point->sender_contact,
                        'sender_phone' => $point->sender_phone,
                        'recipient_name' => $point->recipient_name,
                        'recipient_contact' => $point->recipient_contact,
                        'recipient_phone' => $point->recipient_phone,
                    ]);
            })
            ->values()
            ->all();

        $performers = $this->performersPayloadBuilder->build($order, $financialTerm);
        if ($useWizardState && is_array($wizardState) && filled($wizardState['performers'] ?? null)) {
            $performers = $this->performersPayloadBuilder->mergeFromWizardState($performers, $wizardState['performers']);
        }

        $performers = PerformerRouteActualDates::hydratePerformersFromRoutePoints($performers, $routePoints);
        $performers = $this->performersPayloadBuilder->withFleetLabels($performers);

        $financialTermForNormalize = $financialTerm;
        if ($useWizardState) {
            $wizardCosts = $wizardFt['contractors_costs'] ?? [];
            if (! is_array($wizardCosts)) {
                $wizardCosts = [];
            }

            if ($wizardCosts === []) {
                $financialTermForNormalize = $financialTerm;
            } else {
                $dbCosts = is_array($financialTerm?->contractors_costs) ? $financialTerm->contractors_costs : [];
                $mergedCosts = $this->mergeContractorsCostsSnapshots($dbCosts, $wizardCosts);

                $financialTermForNormalize = new FinancialTerm([
                    'contractors_costs' => $mergedCosts,
                    'client_currency' => $wizardFt['client_currency'] ?? $financialTerm?->client_currency ?? 'RUB',
                ]);
            }
        }

        $contractorsCostsRaw = collect($this->contractorsCostsNormalizer->normalize($order, $financialTermForNormalize, $performers))
            ->values()
            ->all();

        [$contractorsCosts, $migratedAdditionalCosts] = OrderAdditionalCostNormalizer::partitionContractorsCosts(
            $contractorsCostsRaw,
            (string) ($financialTerm?->client_currency ?? 'RUB'),
            optional($order->additional_expenses_payment_date)?->toDateString(),
        );

        $contractorsCosts = collect($contractorsCosts)
            ->map(fn (array $cost): array => [
                'stage' => $cost['stage'] ?? null,
                'carrier_slot' => $cost['carrier_slot'] ?? null,
                'contractor_id' => $cost['contractor_id'] ?? null,
                'amount' => $cost['amount'] ?? null,
                'currency' => $cost['currency'] ?? 'RUB',
                'payment_form' => $this->normalizePaymentFormCodeForWizard($cost['payment_form'] ?? null, 'no_vat'),
                'payment_schedule' => $cost['payment_schedule'] ?? [],
                'payment_terms' => $cost['payment_terms'] ?? '',
            ])
            ->values()
            ->all();

        $additionalCostsSource = $useWizardState
            ? $this->mergeAdditionalCostsSnapshots(
                is_array($financialTerm?->additional_costs) ? $financialTerm->additional_costs : [],
                is_array($wizardFt['additional_costs'] ?? null) ? $wizardFt['additional_costs'] : [],
            )
            : (is_array($financialTerm?->additional_costs) ? $financialTerm->additional_costs : []);

        $additionalCosts = OrderAdditionalCostNormalizer::normalizeList(
            array_merge(
                is_array($additionalCostsSource) ? $additionalCostsSource : [],
                $migratedAdditionalCosts,
            ),
            (string) ($financialTerm?->client_currency ?? 'RUB'),
            optional($order->additional_expenses_payment_date)?->toDateString(),
        );

        return [
            'can_edit_order' => $this->authorization->canEditOrder($request, $order),
            'can_view_order_documents' => OrderDocumentAccessAuthorization::userMayViewDocuments($request->user(), $order),
            'can_manage_order_documents' => $canManageOrderDocuments,
            'can_edit_financial_fields' => OrderFinancialEditAuthorization::userMayEditFinancialFields($request->user(), $order),
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'manual_status' => Schema::hasColumn('orders', 'manual_status') ? $order->manual_status : null,
            'order_date' => optional($order->order_date)?->toDateString(),
            'track_received_date_customer' => Schema::hasColumn('orders', 'track_received_date_customer')
                ? optional($order->track_received_date_customer)?->toDateString()
                : null,
            'track_received_date_carrier' => Schema::hasColumn('orders', 'track_received_date_carrier')
                ? optional($order->track_received_date_carrier)?->toDateString()
                : null,
            'client_id' => $order->customer_id,
            'client_snapshot' => $order->relationLoaded('client') && $order->client !== null
                ? [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'inn' => $order->client->inn,
                    'type' => $order->client->type,
                ]
                : null,
            'own_company_id' => $order->own_company_id,
            'own_company_bank_account_id' => Schema::hasColumn('orders', 'own_company_bank_account_id')
                ? $order->own_company_bank_account_id
                : null,
            'responsible_id' => $this->resolveSerializedOrderOwnerId($order),
            'responsible_name' => $this->resolveSerializedOrderOwnerName($order),
            'order_owner_id' => $this->resolveSerializedOrderOwnerId($order),
            'order_owner_name' => $this->resolveSerializedOrderOwnerName($order),
            'dispatcher_id' => Schema::hasColumn('orders', 'dispatcher_id') ? $order->dispatcher_id : null,
            'dispatcher_name' => $order->relationLoaded('dispatcher') ? $order->dispatcher?->name : null,
            'compensation_split' => is_array($order->metadata) ? ($order->metadata['compensation_split'] ?? null) : null,
            'payment_terms' => $order->payment_terms,
            'special_notes' => $order->special_notes,
            'basic_terms' => $this->serializeBasicTermsForWizard($order),
            'can_promote_basic_terms' => $this->authorization->canPromoteBasicTerms($request),
            'can_direct_promote_basic_terms' => $this->authorization->canDirectPromoteBasicTerms($request),
            'svh_name' => $order->svh_name,
            'svh_address' => Schema::hasColumn('orders', 'svh_address') ? $order->svh_address : null,
            'customs_post_code' => Schema::hasColumn('orders', 'customs_post_code') ? $order->customs_post_code : null,
            'cargo_declared_sum' => Schema::hasColumn('orders', 'cargo_declared_sum') ? $order->cargo_declared_sum : null,
            'customs_declaration_place' => Schema::hasColumn('orders', 'customs_declaration_place') ? $order->customs_declaration_place : null,
            'customs_commodity_code' => Schema::hasColumn('orders', 'customs_commodity_code') ? $order->customs_commodity_code : null,
            'is_international_transport' => Schema::hasColumn('orders', 'is_international_transport')
                ? $order->isInternationalTransportEffective()
                : (bool) data_get($wizardState, 'is_international_transport', false),
            'loading_types' => $this->resolveLoadingTypesForOrder($order),
            'additional_expenses' => Schema::hasColumn('orders', 'additional_expenses') ? $order->additional_expenses : null,
            'additional_expenses_payment_date' => Schema::hasColumn('orders', 'additional_expenses_payment_date')
                ? optional($order->additional_expenses_payment_date)?->toDateString()
                : null,
            'insurance' => Schema::hasColumn('orders', 'insurance') ? $order->insurance : null,
            'bonus' => Schema::hasColumn('orders', 'bonus') ? $order->bonus : null,
            'performers' => $performers,
            'route_points' => $routePoints,
            'cargo_items' => $this->hydrateCargoItemsForWizard(
                $this->mergeCargoItemsWithWizardState(
                    $cargoItems->map(fn ($cargo): array => [
                        'id' => $cargo->id,
                        'name' => $cargo->ati_cargo_name ?: $cargo->title,
                        'description' => $cargo->description,
                        'weight_value' => $cargo->weight_value ?? $cargo->weight,
                        'weight_kg' => $cargo->weight_value ?? $cargo->weight,
                        'weight_unit' => $cargo->weight_unit ?: 'kg',
                        'volume_m3' => $cargo->volume,
                        'length_m' => $cargo->length,
                        'width_m' => $cargo->width,
                        'height_m' => $cargo->height,
                        'diameter_m' => $cargo->diameter,
                        'package_type' => $cargo->packing_type,
                        'pack_type_id' => $cargo->pack_type_id,
                        'pack_type_label' => $cargo->pack_type_label,
                        'loading_type_id' => $cargo->loading_type_id,
                        'loading_type_code' => $cargo->loading_type_code,
                        'loading_type_label' => $cargo->loading_type_label,
                        'loading_type_items' => $cargo->loading_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'loading_type'),
                        'truck_body_type_id' => $cargo->truck_body_type_id,
                        'truck_body_type_code' => $cargo->truck_body_type_code,
                        'truck_body_type_label' => $cargo->truck_body_type_label,
                        'truck_body_type_items' => $cargo->truck_body_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'truck_body_type'),
                        'trailer_type_id' => $cargo->trailer_type_id,
                        'trailer_type_code' => $cargo->trailer_type_code,
                        'trailer_type_label' => $cargo->trailer_type_label,
                        'trailer_type_items' => $cargo->trailer_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'trailer_type'),
                        'package_count' => $cargo->package_count ?? $cargo->pallet_count,
                        'dangerous_goods' => $cargo->is_hazardous,
                        'dangerous_class' => $cargo->hazard_class,
                        'hs_code' => $cargo->hs_code,
                        'cargo_type' => $cargo->cargo_type ?: 'general',
                        'cargo_type_id' => $cargo->cargo_type_id,
                        'cargo_type_label' => $cargo->cargo_type_label,
                        'is_oversized' => $cargo->is_oversized,
                        'is_fragile' => $cargo->is_fragile,
                        'ati_cargo_payload' => $this->atiCargoPayloadForWizard($cargo->ati_cargo_payload),
                        'performer_allocations' => $this->performerAllocationsFromCargoPayload($cargo->ati_cargo_payload),
                    ])->values()->all(),
                    $useWizardState && is_array($wizardState) ? ($wizardState['cargo_items'] ?? []) : [],
                ),
                $performers,
            ),
            'financial_term' => [
                'client_price' => $useWizardState
                    ? $this->resolveClientPriceForWizardPayload($wizardFt, $order, $financialTerm)
                    : ($order->customer_rate !== null
                        ? $order->customer_rate
                        : $financialTerm?->client_price),
                'client_currency' => $useWizardState
                    ? ($wizardFt['client_currency'] ?? 'RUB')
                    : ($financialTerm?->client_currency ?? 'RUB'),
                'client_payment_form' => $this->normalizePaymentFormCodeForWizard(
                    $useWizardState
                        ? ($wizardFt['client_payment_form'] ?? $order->customer_payment_form)
                        : $order->customer_payment_form,
                    PaymentFormDictionary::defaultClientVatCode(),
                ),
                'client_request_mode' => data_get($paymentTermsConfig, 'client.request_mode', 'single_request'),
                'client_payment_schedule' => $paymentTermsConfig['client']['payment_schedule'] ?? [],
                'client_payment_terms' => $useWizardState
                    ? $this->resolveClientPaymentTermsForWizardPayload($wizardFt, $financialTerm, $order)
                    : (string) ($financialTerm?->client_payment_terms ?? $order->customer_payment_term ?? ''),
                'contractors_costs' => $contractorsCosts,
                'additional_costs' => $additionalCosts,
                'kpi_percent' => $order->kpi_percent ?? ($useWizardState ? ($wizardFt['kpi_percent'] ?? 0) : 0),
                'client_norms_penalties' => $useWizardState
                    ? (is_array($wizardFt['client_norms_penalties'] ?? null) ? $wizardFt['client_norms_penalties'] : [])
                    : [],
                'carrier_norms_by_leg' => $useWizardState
                    ? (is_array($wizardFt['carrier_norms_by_leg'] ?? null) ? $wizardFt['carrier_norms_by_leg'] : [])
                    : [],
            ],
            'payment_settlement' => app(PaymentSettlementSummaryBuilder::class)->forOrder($order),
            'print_form_template_selection' => is_array($wizardState) && is_array($wizardState['print_form_template_selection'] ?? null)
                ? $wizardState['print_form_template_selection']
                : [],
            'documents' => $documents->map(fn (OrderDocument $document): array => $this->documentSerializer->serialize(
                $document,
                $order,
                $canManageOrderDocuments,
                $canApproveOrderDocuments,
                $templatesById
            ))->values()->all(),
            'status_logs' => $statusLogs->map(fn ($log): array => [
                'id' => $log->id,
                'status_from' => $log->status_from,
                'status_to' => $log->status_to,
                'comment' => $log->comment,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])->values()->all(),
            'smart_links' => [],
            'lead_precalculation_snapshot' => OrderLeadPrecalculationSnapshotResolver::forWizard($order),
        ];
    }

    /**
     * Приводит форму оплаты к кодам валидации мастера (vat / no_vat / cash), т.к. в БД и старых снимках могли быть подписи («с НДС» и т.п.).
     *
     * @param  'vat'|'no_vat'|'cash'  $default
     */
    private function normalizePaymentFormCodeForWizard(?string $value, string $default = 'no_vat'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $trimmed = trim($value);
        $allowed = PaymentFormDictionary::allowedCodesForValidation();
        if (in_array($trimmed, $allowed, true)) {
            $normalized = PaymentFormDictionary::normalizeForStorage($trimmed);

            return $normalized ?? $default;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (str_contains($lower, 'без') && str_contains($lower, 'ндс')) {
            return 'no_vat';
        }

        if (str_contains($lower, 'нал')) {
            return 'cash';
        }

        if (str_contains($lower, 'ндс')) {
            return PaymentFormDictionary::defaultClientVatCode();
        }

        return $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveWizardState(Order $order): ?array
    {
        if (! Schema::hasColumn('orders', 'wizard_state')) {
            return null;
        }

        $payload = $order->wizard_state;
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function atiCargoPayloadForWizard(mixed $payload): array
    {
        if (! is_array($payload) || $payload === [] || array_is_list($payload)) {
            return [];
        }

        return $payload;
    }

    /**
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    private function performerAllocationsFromCargoPayload(mixed $payload): array
    {
        $normalizedPayload = $this->atiCargoPayloadForWizard($payload);
        $raw = $normalizedPayload['performer_allocations'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        return CargoPerformerAllocationNormalizer::normalizeForStorage($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $cargoItems
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function hydrateCargoItemsForWizard(array $cargoItems, array $performers): array
    {
        $performerRows = array_values(array_filter($performers, static fn (mixed $row): bool => is_array($row)));

        return collect($cargoItems)
            ->map(fn (array $cargo): array => OrderCargoItemsPayloadNormalizer::normalizeCargoItem($cargo, $performerRows))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $cargoItems
     * @return list<array<string, mixed>>
     */
    private function mergeCargoItemsWithWizardState(array $cargoItems, mixed $wizardCargoItems): array
    {
        if (! is_array($wizardCargoItems) || $wizardCargoItems === []) {
            return $cargoItems;
        }

        $wizardByName = collect($wizardCargoItems)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => mb_strtolower(trim((string) ($row['name'] ?? ''))));

        return collect($cargoItems)
            ->map(function (array $cargo) use ($wizardByName): array {
                $dbAllocations = is_array($cargo['performer_allocations'] ?? null)
                    ? $cargo['performer_allocations']
                    : [];
                if ($dbAllocations !== []) {
                    $cargo['performer_allocations'] = CargoPerformerAllocationNormalizer::normalizeForStorage($dbAllocations);
                    $atiPayload = is_array($cargo['ati_cargo_payload'] ?? null) ? $cargo['ati_cargo_payload'] : [];
                    $atiPayload['performer_allocations'] = $cargo['performer_allocations'];
                    $cargo['ati_cargo_payload'] = $atiPayload;

                    return $cargo;
                }

                $key = mb_strtolower(trim((string) ($cargo['name'] ?? '')));
                $wizardRow = $wizardByName->get($key);
                if (! is_array($wizardRow)) {
                    return $cargo;
                }

                $wizardAllocations = is_array($wizardRow['performer_allocations'] ?? null)
                    ? CargoPerformerAllocationNormalizer::normalizeForStorage($wizardRow['performer_allocations'])
                    : [];
                if ($wizardAllocations === []) {
                    return $cargo;
                }

                $cargo['performer_allocations'] = $wizardAllocations;
                $atiPayload = is_array($cargo['ati_cargo_payload'] ?? null) ? $cargo['ati_cargo_payload'] : [];
                $atiPayload['performer_allocations'] = $wizardAllocations;
                $cargo['ati_cargo_payload'] = $atiPayload;

                return $cargo;
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Cargo>
     */
    private function orderCargoItems(Order $order): Collection
    {
        if (Schema::hasColumn('cargos', 'order_id')) {
            return $order->cargoItems;
        }

        return Cargo::query()
            ->select('cargos.*')
            ->join('cargo_leg', 'cargo_leg.cargo_id', '=', 'cargos.id')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->where('order_legs.order_id', $order->id)
            ->orderBy('cargos.id')
            ->get();
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string|null}>
     */
    private function dictionaryItemsFromFlatCargo(Cargo $cargo, string $prefix): array
    {
        $item = [
            'id' => $cargo->{$prefix.'_id'} ?? null,
            'code' => $cargo->{$prefix.'_code'} ?? null,
            'label' => $cargo->{$prefix.'_label'} ?? null,
        ];

        return $item['id'] !== null || $item['code'] !== null || $item['label'] !== null ? [$item] : [];
    }

    /**
     * @return list<string>
     */
    private function resolveLoadingTypesForOrder(Order $order): array
    {
        $types = collect($order->legs ?? [])
            ->flatMap(fn ($leg) => $leg->routePoints ?? [])
            ->filter(fn ($point): bool => ($point->type ?? null) === 'loading')
            ->flatMap(function ($point): array {
                $fromMetadata = data_get($point->metadata, 'loading_types', []);
                if (is_array($fromMetadata)) {
                    return $fromMetadata;
                }

                $fromNormalized = data_get($point->normalized_data, 'loading_types', []);

                return is_array($fromNormalized) ? $fromNormalized : [];
            })
            ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
            ->filter()
            ->unique()
            ->values();

        if ($types->isEmpty()) {
            $fallback = data_get($order->metadata, 'loading_types', []);
            if (is_array($fallback)) {
                $types = collect($fallback)
                    ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        if ($types->isEmpty()) {
            $fallback = data_get($order->wizard_state, 'loading_types', []);
            if (is_array($fallback)) {
                $types = collect($fallback)
                    ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        return $types->all();
    }

    private function normalizeLoadingType(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            'top', 'верх' => 'top',
            'side', 'бок' => 'side',
            'rear', 'зад' => 'rear',
            'full', 'растентовка', 'полная растентовка' => 'full',
            'tail_lift', 'hydraulic', 'гидроборт' => 'tail_lift',
            'crane', 'manipulator', 'манипулятор' => 'crane',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $dbCosts
     * @param  list<array<string, mixed>>  $wizardCosts
     * @return list<array<string, mixed>>
     */
    private function mergeContractorsCostsSnapshots(array $dbCosts, array $wizardCosts): array
    {
        $byKey = [];

        foreach ($dbCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $byKey[$this->performersPayloadBuilder->contractorCostSnapshotKey($row)] = $row;
        }

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $this->performersPayloadBuilder->contractorCostSnapshotKey($row);
            $base = $byKey[$key] ?? [];
            $merged = array_merge($base, $row);

            if (! array_key_exists('contractor_id', $row) || $row['contractor_id'] === null || $row['contractor_id'] === '') {
                $merged['contractor_id'] = $base['contractor_id'] ?? null;
            }

            if (! array_key_exists('amount', $row) || $row['amount'] === null || $row['amount'] === '') {
                $merged['amount'] = $base['amount'] ?? null;
            }

            if (! array_key_exists('currency', $row) || $row['currency'] === null || $row['currency'] === '') {
                $merged['currency'] = $base['currency'] ?? 'RUB';
            }

            if (! array_key_exists('payment_form', $row) || $row['payment_form'] === null || $row['payment_form'] === '') {
                $merged['payment_form'] = $base['payment_form'] ?? null;
            }

            if (! array_key_exists('payment_schedule', $row) || $row['payment_schedule'] === null
                || (is_array($row['payment_schedule']) && $row['payment_schedule'] === [])) {
                $schedule = $base['payment_schedule'] ?? [];
                $merged['payment_schedule'] = is_array($schedule) ? $schedule : [];
            } elseif ($this->shouldPreserveBaseContractorCostPaymentSchedule(
                $row['payment_schedule'] ?? null,
                $base['payment_schedule'] ?? null,
            )) {
                $merged['payment_schedule'] = $base['payment_schedule'];
            }

            if (! array_key_exists('payment_terms', $row) || trim((string) ($row['payment_terms'] ?? '')) === '') {
                $merged['payment_terms'] = $base['payment_terms'] ?? '';
            }

            $merged['stage'] = $row['stage'] ?? $base['stage'] ?? null;
            $byKey[$key] = $merged;
        }

        return array_values($byKey);
    }

    /**
     * @param  list<array<string, mixed>>  $dbCosts
     * @param  list<array<string, mixed>>  $wizardCosts
     * @return list<array<string, mixed>>
     */
    private function mergeAdditionalCostsSnapshots(array $dbCosts, array $wizardCosts): array
    {
        $byId = [];

        foreach ($dbCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $byId[$key] = $row;
        }

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $base = $byId[$key] ?? [];
            $merged = array_merge($base, $row);

            foreach (['contractor_id', 'contractor_name', 'service_date', 'amount', 'currency', 'payment_form', 'payment_terms'] as $field) {
                if (! array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                    if (array_key_exists($field, $base)) {
                        $merged[$field] = $base[$field];
                    }
                }
            }

            if (! array_key_exists('payment_schedule', $row)
                || $row['payment_schedule'] === null
                || (is_array($row['payment_schedule']) && $row['payment_schedule'] === [])) {
                $schedule = $base['payment_schedule'] ?? [];
                $merged['payment_schedule'] = is_array($schedule) ? $schedule : [];
            }

            $byId[$key] = $merged;
        }

        if ($wizardCosts === [] && $dbCosts !== []) {
            return array_values($byId);
        }

        if ($wizardCosts !== [] && $byId === []) {
            return array_values(array_filter($wizardCosts, static fn (mixed $row): bool => is_array($row)));
        }

        $ordered = [];

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key !== '' && isset($byId[$key])) {
                $ordered[] = $byId[$key];

                continue;
            }

            $ordered[] = $row;
        }

        foreach ($byId as $key => $row) {
            $alreadyIncluded = collect($ordered)->contains(
                fn (array $existing): bool => trim((string) ($existing['id'] ?? '')) === $key,
            );

            if (! $alreadyIncluded) {
                $ordered[] = $row;
            }
        }

        return $ordered;
    }

    private function shouldPreserveBaseContractorCostPaymentSchedule(mixed $wizardSchedule, mixed $baseSchedule): bool
    {
        if (! is_array($baseSchedule) || $baseSchedule === []) {
            return false;
        }

        if (! is_array($wizardSchedule) || $wizardSchedule === []) {
            return true;
        }

        $baseInstallments = isset($baseSchedule['installments']) && is_array($baseSchedule['installments'])
            ? $baseSchedule['installments']
            : [];
        $wizardInstallments = isset($wizardSchedule['installments']) && is_array($wizardSchedule['installments'])
            ? $wizardSchedule['installments']
            : [];

        if ($baseInstallments !== [] && $wizardInstallments === []) {
            return true;
        }

        if ($baseInstallments === [] && $wizardInstallments === []) {
            $baseDays = (int) ($baseSchedule['postpayment_days'] ?? 0);
            $wizardDays = (int) ($wizardSchedule['postpayment_days'] ?? 0);

            return $baseDays > 0 && $wizardDays === 0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $wizardFt
     */
    private function resolveClientPriceForWizardPayload(array $wizardFt, Order $order, ?FinancialTerm $financialTerm): ?float
    {
        if (array_key_exists('client_price', $wizardFt)) {
            $fromWizard = $wizardFt['client_price'];
            if ($fromWizard !== null && $fromWizard !== '' && is_numeric($fromWizard) && (float) $fromWizard > 0) {
                return round((float) $fromWizard, 2);
            }
        }

        if ($order->customer_rate !== null && is_numeric($order->customer_rate) && (float) $order->customer_rate > 0) {
            return round((float) $order->customer_rate, 2);
        }

        if ($financialTerm?->client_price !== null && is_numeric($financialTerm->client_price) && (float) $financialTerm->client_price > 0) {
            return round((float) $financialTerm->client_price, 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $wizardFt
     */
    private function resolveClientPaymentTermsForWizardPayload(
        array $wizardFt,
        ?FinancialTerm $financialTerm,
        Order $order,
    ): string {
        $fromFt = trim((string) ($financialTerm?->client_payment_terms ?? ''));
        if ($fromFt !== '') {
            return $fromFt;
        }

        $fromWizard = trim((string) ($wizardFt['client_payment_terms'] ?? ''));
        if ($fromWizard !== '') {
            return $fromWizard;
        }

        return trim((string) ($order->customer_payment_term ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePaymentTermsConfig(?string $paymentTerms): array
    {
        if (blank($paymentTerms)) {
            return [];
        }

        try {
            $decoded = json_decode($paymentTerms, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array{customer: array<string, mixed>, carrier: array<string, mixed>}|null
     */
    private function serializeBasicTermsForWizard(Order $order): ?array
    {
        /** @var PrintFormBasicTermsService $service */
        $service = app(PrintFormBasicTermsService::class);

        if (! $service->tablesReady()) {
            return null;
        }

        return [
            'customer' => $service->wizardPayloadForOrder($order, PrintFormBasicTerm::PARTY_CUSTOMER),
            'carrier' => $service->wizardPayloadForOrder($order, PrintFormBasicTerm::PARTY_CARRIER),
        ];
    }

    private function resolveSerializedOrderOwnerId(Order $order): ?int
    {
        if (Schema::hasColumn('orders', 'order_owner_id') && $order->order_owner_id !== null) {
            return (int) $order->order_owner_id;
        }

        return $order->manager_id !== null ? (int) $order->manager_id : null;
    }

    private function resolveSerializedOrderOwnerName(Order $order): ?string
    {
        if (
            Schema::hasColumn('orders', 'order_owner_id')
            && $order->order_owner_id !== null
            && $order->relationLoaded('orderOwner')
        ) {
            return $order->orderOwner?->name;
        }

        return $order->relationLoaded('manager') ? $order->manager?->name : null;
    }
}
