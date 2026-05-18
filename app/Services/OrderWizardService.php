<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\LegContractorAssignment;
use App\Models\LegCost;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\OrderStatusLog;
use App\Models\RoutePoint;
use App\Models\User;
use App\Support\CarrierPaymentFormResolver;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CashToCashMarginCalculator;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentInstallmentPlanner;
use App\Support\PaymentInstallmentScheduleNormalizer;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\RoutePointNormalizedData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;

class OrderWizardService
{
    /**
     * Кэш имён колонок таблицы `orders` на время жизни экземпляра (запрос / джоба), вместо N вызовов Schema::hasColumn.
     *
     * @var array<string, true>|null
     */
    private ?array $ordersColumnLookup = null;

    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderCompensationService $orderCompensationService,
        private readonly OrderWizardStateService $orderWizardStateService,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): Order
    {
        return DB::transaction(function () use ($validated, $user): Order {
            $validated = $this->normalizeValidatedPaymentForms($validated);
            $ownCompany = $this->resolveOwnCompany($validated);
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany)['company_code'], 'order_number' => $validated['order_number']];

            $order = Order::query()->create($this->extractOrderAttributes($validated, $user, $generatedNumber, true, null, null));

            $this->syncNestedData($order, $validated, $user);
            $this->orderCompensationService->recalculateImpactedPeriods($order->fresh());
            $this->syncDerivedStatus($order, $validated, $user, null);

            $this->orderWizardStateService->persistFromValidated($order->fresh(), $validated);

            return $order->fresh()->load($this->relationsForOrderReload());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Order $order, array $validated, User $user): Order
    {
        return DB::transaction(function () use ($order, $validated, $user): Order {
            $validated = $this->normalizeValidatedPaymentForms($validated);
            $previousStatus = $order->status;
            $previousOrderDate = optional($order->order_date)?->toDateString();
            $previousManagerId = $order->manager_id;

            // Check if deal type changed
            $oldDealType = $this->orderCompensationService->calculateOrder($order)['deal_type'];
            $ownCompany = $this->resolveOwnCompany($validated);
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany)['company_code'], 'order_number' => $validated['order_number']];

            $existingMetadata = is_array($order->metadata) ? $order->metadata : null;
            $order->update($this->extractOrderAttributes($validated, $user, $generatedNumber, false, $order->manager_id, $existingMetadata));

            $this->syncNestedData($order, $validated, $user);

            $updatedOrder = $order->fresh();
            $newDealType = $this->orderCompensationService->calculateOrder($updatedOrder)['deal_type'];
            $dealTypeChanged = $oldDealType !== $newDealType && $oldDealType !== 'unknown' && $newDealType !== 'unknown';

            $this->orderCompensationService->recalculateImpactedPeriods($updatedOrder, $previousManagerId, $previousOrderDate, $dealTypeChanged);
            $this->syncDerivedStatus($updatedOrder, $validated, $user, $previousStatus);

            $this->orderWizardStateService->persistFromValidated($updatedOrder->fresh(), $validated);

            return $updatedOrder->fresh()->load($this->relationsForOrderReload());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{company_code: string, order_number: string}  $numberData
     * @return array<string, mixed>
     */
    private function extractOrderAttributes(
        array $validated,
        User $user,
        array $numberData,
        bool $isCreating,
        ?int $existingManagerId = null,
        ?array $existingMetadata = null,
    ): array {
        $financialTerm = Arr::get($validated, 'financial_term', []);
        $contractorCosts = Arr::get($financialTerm, 'contractors_costs', []);
        $performers = $this->resolvedPerformers($validated);
        $routePoints = collect($validated['route_points'] ?? [])->sortBy('sequence')->values();
        $firstLoading = $routePoints->firstWhere('type', 'loading');
        $firstLoadingDate = $this->resolveRoutePointDateForOrderAggregate($firstLoading);
        $lastUnloading = $routePoints->where('type', 'unloading')->last();
        $lastUnloadingDate = $this->resolveRoutePointDateForOrderAggregate($lastUnloading);
        $performerTotal = collect($contractorCosts)->sum(fn (array $performer): float => (float) ($performer['amount'] ?? 0));
        $clientPrice = (float) Arr::get($financialTerm, 'client_price', 0);
        $clientPaymentSchedule = Arr::get($financialTerm, 'client_payment_schedule', []);
        $dateContext = PaymentInstallmentPlanner::dateContextFromWizardPayload($validated);
        $clientCurrency = (string) Arr::get($financialTerm, 'client_currency', 'RUB');
        $manualClientTerms = trim((string) Arr::get($financialTerm, 'client_payment_terms', ''));
        $formattedClientSummary = $this->formatPaymentScheduleSummary(
            is_array($clientPaymentSchedule) ? $clientPaymentSchedule : [],
            $clientPrice,
            $clientCurrency,
            null,
            $dateContext,
        );
        $clientPaymentSummary = $manualClientTerms !== ''
            ? Str::limit($manualClientTerms, 255, '')
            : Str::limit($formattedClientSummary, 2000, '');
        $carrierPaymentForm = CarrierPaymentFormResolver::fromContractorsCostsArray($contractorCosts);
        $carrierPaymentRaw = $this->resolveCarrierPaymentTerm($contractorCosts);
        $carrierPaymentSummary = ($carrierPaymentRaw !== null && $carrierPaymentRaw !== '')
            ? Str::limit($carrierPaymentRaw, 2000, '')
            : null;

        $normalizedPerformers = collect($performers)
            ->map(function (array $performer): array {
                if (isset($performer['contractor_id']) && $performer['contractor_id'] !== null) {
                    $performer['contractor_id'] = (int) $performer['contractor_id'];
                }

                return $performer;
            })
            ->all();

        // Преобразуем contractor_id из строки в integer для carrier_id
        $carrierId = collect($normalizedPerformers)->pluck('contractor_id')->filter()->first();
        $carrierId = $carrierId !== null ? (int) $carrierId : null;

        $attributes = [
            'order_number' => $numberData['order_number'],
            'company_code' => $numberData['company_code'],
            'manager_id' => $isCreating ? $user->id : $existingManagerId,
            'order_date' => $validated['order_date'],
            'loading_date' => $firstLoadingDate,
            'unloading_date' => $lastUnloadingDate,
            'customer_id' => $validated['client_id'],
            'own_company_id' => $validated['own_company_id'] ?? null,
            'own_company_bank_account_id' => $this->nullIfTrimmedEmpty($validated['own_company_bank_account_id'] ?? null),
            'carrier_id' => $carrierId,
            'customer_rate' => $clientPrice ?: null,
            'customer_payment_form' => Arr::get($financialTerm, 'client_payment_form'),
            'customer_payment_term' => $clientPaymentSummary,
            'payment_terms' => $this->encodePaymentTermsPayload($financialTerm),
            'special_notes' => $validated['special_notes'] ?? null,
            'svh_name' => $validated['svh_name'] ?? null,
            'svh_address' => $validated['svh_address'] ?? null,
            'customs_post_code' => $this->nullIfTrimmedEmpty($validated['customs_post_code'] ?? null),
            'customs_post_name' => null,
            'customs_declaration_place' => null,
            'customs_commodity_code' => null,
            'is_international_transport' => (bool) ($validated['is_international_transport'] ?? false),
            'carrier_rate' => $performerTotal ?: null,
            'carrier_payment_form' => $carrierPaymentForm,
            'carrier_payment_term' => $carrierPaymentSummary,
            'kpi_percent' => 0,
            'delta' => 0,
            'salary_accrued' => 0,
            'status' => $validated['status'],
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'is_active' => true,
            'performers' => $normalizedPerformers,
            'updated_by' => $user->id,
            ...($isCreating ? ['created_by' => $user->id] : []),
        ];

        $metadata = is_array($existingMetadata) ? $existingMetadata : [];
        $loadingTypes = array_values(array_filter(
            array_map(
                fn (mixed $value): ?string => $this->normalizeLoadingType($value),
                is_array($validated['loading_types'] ?? null) ? $validated['loading_types'] : []
            )
        ));

        if ($loadingTypes === []) {
            unset($metadata['loading_types']);
        } else {
            $metadata['loading_types'] = array_values(array_unique($loadingTypes));
        }

        $attributes['metadata'] = $metadata;

        foreach (['additional_expenses', 'insurance', 'bonus'] as $key) {
            if (! $isCreating && ! array_key_exists($key, $validated)) {
                continue;
            }

            $raw = $validated[$key] ?? null;
            $attributes[$key] = $raw !== null && $raw !== '' ? (float) $raw : 0.0;
        }

        return $this->onlyExistingOrderColumns($attributes);
    }

    /**
     * Дата на заказе: факт точки маршрута, иначе план (для расчёта оплат и агрегата в `orders`).
     *
     * @param  array<string, mixed>|null  $point
     */
    private function resolveRoutePointDateForOrderAggregate(?array $point): ?string
    {
        if ($point === null) {
            return null;
        }

        $actual = $point['actual_date'] ?? null;
        if (filled($actual)) {
            return is_string($actual) ? substr($actual, 0, 10) : (string) $actual;
        }

        $planned = $point['planned_date'] ?? null;
        if (filled($planned)) {
            return is_string($planned) ? substr($planned, 0, 10) : (string) $planned;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function resolvedPerformers(array $validated): array
    {
        return $this->mergePerformerFleetFields(
            $this->performersForLegSync($validated),
            $validated['performers'] ?? []
        );
    }

    /**
     * @param  list<array<string, mixed>>  $syncedPerformers
     * @param  array<int, mixed>  $formPerformers
     * @return list<array<string, mixed>>
     */
    private function mergePerformerFleetFields(array $syncedPerformers, array $formPerformers): array
    {
        $byStage = collect($formPerformers)
            ->filter(fn (mixed $p): bool => is_array($p))
            ->map(fn (array $p): array => $p)
            ->keyBy(fn (array $p): string => $this->normalizeStageIdentifier((string) ($p['stage'] ?? '')));

        return collect($syncedPerformers)
            ->map(function (array $p) use ($byStage): array {
                $stage = $this->normalizeStageIdentifier((string) ($p['stage'] ?? ''));
                $extra = $byStage->get($stage);
                if (! is_array($extra)) {
                    return $p;
                }
                foreach (['fleet_vehicle_id', 'fleet_driver_id'] as $key) {
                    if (! array_key_exists($key, $extra)) {
                        continue;
                    }
                    $val = $extra[$key];
                    $p[$key] = $val !== null && $val !== '' ? (int) $val : null;
                }

                return $p;
            })
            ->all();
    }

    /**
     * Исполнители по этапам: приоритет у `financial_term.contractors_costs`; если пусто — массив `performers` из запроса.
     *
     * @return list<array{stage: string, contractor_id: int|null}>
     */
    private function performersForLegSync(array $validated): array
    {
        $financialTerm = Arr::get($validated, 'financial_term', []);
        $costs = Arr::get($financialTerm, 'contractors_costs', []);

        if (is_array($costs) && $costs !== []) {
            return collect($costs)
                ->map(function (array $cost): array {
                    $id = $cost['contractor_id'] ?? null;

                    return [
                        'stage' => (string) ($cost['stage'] ?? 'leg_1'),
                        'contractor_id' => $id !== null && $id !== '' ? (int) $id : null,
                    ];
                })
                ->values()
                ->all();
        }

        return collect($validated['performers'] ?? [])
            ->map(function ($performer): array {
                if (! is_array($performer)) {
                    return ['stage' => 'leg_1', 'contractor_id' => null];
                }

                $id = $performer['contractor_id'] ?? null;

                return [
                    'stage' => (string) ($performer['stage'] ?? 'leg_1'),
                    'contractor_id' => $id !== null && $id !== '' ? (int) $id : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Order $order, array $validated, User $user): void
    {
        $hasOrderDocuments = Schema::hasTable('order_documents');

        $order->loadMissing($this->relationsForNestedSync());

        $performers = $this->resolvedPerformers($validated);
        $normalizedPerformers = collect($performers)
            ->map(function (array $performer): array {
                if (isset($performer['contractor_id']) && $performer['contractor_id'] !== null) {
                    $performer['contractor_id'] = (int) $performer['contractor_id'];
                }

                return $performer;
            })
            ->all();

        $legs = $this->syncLegs($order, $normalizedPerformers);
        $primaryLeg = $legs->first();
        $legsByStage = $legs->keyBy(fn (OrderLeg $leg): string => $this->normalizeStageIdentifier($leg->description));
        $routePoints = collect($validated['route_points'] ?? [])
            ->sortBy('sequence')
            ->values();
        $isInternationalTransport = (bool) ($validated['is_international_transport'] ?? false);
        if (! $isInternationalTransport) {
            $routePoints = $routePoints
                ->filter(fn (array $point): bool => trim((string) ($point['type'] ?? '')) !== 'border_crossing')
                ->values();
        }
        $loadingTypes = array_values(array_filter(
            array_map(
                fn (mixed $value): ?string => $this->normalizeLoadingType($value),
                is_array($validated['loading_types'] ?? null) ? $validated['loading_types'] : []
            )
        ));
        $loadingTypes = array_values(array_unique($loadingTypes));
        $routePointSequenceByLeg = [];

        // Синхронизируем назначения исполнителей и стоимость
        $this->syncLegContractorAssignmentsAndCosts($order, $legs, $validated, $user);

        $this->deleteExistingCargoItems($order);

        if ($hasOrderDocuments) {
            $order->documents()
                ->get()
                ->each(function (OrderDocument $document): void {
                    if (! $this->isPrintWorkflowDocument($document) || $this->isEmptyPrintWorkflowArtifact($document)) {
                        $document->delete();
                    }
                });
        }

        foreach ($routePoints as $index => $routePoint) {
            $routePointType = trim((string) ($routePoint['type'] ?? ''));
            $routePointAddress = trim((string) ($routePoint['address'] ?? ''));
            $routePointPlannedDate = trim((string) ($routePoint['planned_date'] ?? ''));
            if ($routePointType === '') {
                continue;
            }

            $isBorderCrossing = $routePointType === 'border_crossing';
            if ($routePointAddress === '' && ! ($isBorderCrossing && $routePointPlannedDate !== '')) {
                continue;
            }

            if ($isBorderCrossing && $routePointAddress === '') {
                $routePointAddress = 'Прохождение границы';
            }

            if ($primaryLeg === null) {
                break;
            }

            $routePointStage = $this->normalizeStageIdentifier((string) ($routePoint['stage'] ?? ''));
            $targetLeg = $legsByStage->get($routePointStage, $primaryLeg);
            $normalizedData = Arr::get($routePoint, 'normalized_data', []);
            if (! is_array($normalizedData)) {
                $normalizedData = [];
            }
            $normalizedData = RoutePointNormalizedData::prepareForStorage($normalizedData, $routePointAddress);
            if ($routePointType === 'loading') {
                if ($loadingTypes !== []) {
                    $normalizedData['loading_types'] = $loadingTypes;
                } else {
                    unset($normalizedData['loading_types']);
                }
            }
            $legSequence = ($routePointSequenceByLeg[$targetLeg->id] ?? 0) + 1;
            $routePointSequenceByLeg[$targetLeg->id] = $legSequence;

            $routePointAttributes = [
                'order_leg_id' => $targetLeg->id,
                'type' => $routePointType,
                'sequence' => $legSequence,
                'kladr_id' => Arr::get($normalizedData, 'kladr_id'),
                'latitude' => Arr::get($normalizedData, 'coordinates.lat'),
                'longitude' => Arr::get($normalizedData, 'coordinates.lng'),
                'planned_date' => $routePoint['planned_date'] ?? null,
                'actual_date' => $routePoint['actual_date'] ?? null,
                'contact_person' => $routePoint['contact_person'] ?? null,
                'contact_phone' => $routePoint['contact_phone'] ?? null,
                'sender_name' => $routePoint['sender_name'] ?? null,
                'sender_contact' => $routePoint['sender_contact'] ?? null,
                'sender_phone' => $routePoint['sender_phone'] ?? null,
                'recipient_name' => $routePoint['recipient_name'] ?? null,
                'recipient_contact' => $routePoint['recipient_contact'] ?? null,
                'recipient_phone' => $routePoint['recipient_phone'] ?? null,
            ];

            if (Schema::hasColumn('route_points', 'planned_time_from')) {
                $routePointAttributes['planned_time_from'] = $this->normalizeTime($routePoint['planned_time_from'] ?? null);
            }
            if (Schema::hasColumn('route_points', 'planned_time_to')) {
                $routePointAttributes['planned_time_to'] = $this->normalizeTime($routePoint['planned_time_to'] ?? null);
            }
            if (Schema::hasColumn('route_points', 'actual_time')) {
                $routePointAttributes['actual_time'] = $this->normalizeTime($routePoint['actual_time'] ?? null);
            }

            if (Schema::hasColumn('route_points', 'address')) {
                $routePointAttributes['address'] = $routePointAddress;
            }

            if (Schema::hasColumn('route_points', 'normalized_data')) {
                $routePointAttributes['normalized_data'] = $normalizedData;
            } elseif (Schema::hasColumn('route_points', 'metadata')) {
                $routePointAttributes['metadata'] = [
                    'address' => $routePointAddress,
                    'normalized_data' => $normalizedData,
                ];
            } elseif (Schema::hasColumn('route_points', 'instructions')) {
                $routePointAttributes['instructions'] = $routePointAddress;
            }

            RoutePoint::query()->create($routePointAttributes);
        }

        foreach ($validated['cargo_items'] ?? [] as $cargoItem) {
            $cargoTitle = trim((string) ($cargoItem['name'] ?? ''));
            $cargoType = $cargoItem['cargo_type'] ?? null;
            if ($cargoTitle === '' || $cargoType === null || $cargoType === '') {
                continue;
            }

            $weightValue = $this->normalizeNullableFloat($cargoItem['weight_value'] ?? $cargoItem['weight_kg'] ?? null);
            $weightUnit = ($cargoItem['weight_unit'] ?? 'kg') === 't' ? 't' : 'kg';
            $weightKg = $weightValue;
            if ($weightKg !== null && $weightUnit === 't') {
                $weightKg = $weightKg * 1000;
            }

            $length = $this->normalizeNullableFloat($cargoItem['length_m'] ?? null);
            $width = $this->normalizeNullableFloat($cargoItem['width_m'] ?? null);
            $height = $this->normalizeNullableFloat($cargoItem['height_m'] ?? null);
            $volume = $length !== null && $width !== null && $height !== null && $length > 0 && $width > 0 && $height > 0
                ? round($length * $width * $height, 3)
                : $this->normalizeNullableFloat($cargoItem['volume_m3'] ?? null);
            $cargoType = $this->nullIfTrimmedEmpty($cargoType);
            $packType = $this->nullIfTrimmedEmpty($cargoItem['package_type'] ?? null);
            $dictionaryItems = [
                'loading_type' => $this->normalizeDictionaryItems($cargoItem['loading_type_items'] ?? null),
                'truck_body_type' => $this->normalizeDictionaryItems($cargoItem['truck_body_type_items'] ?? null),
                'trailer_type' => $this->normalizeDictionaryItems($cargoItem['trailer_type_items'] ?? null),
            ];
            $primaryDictionaryItems = [
                'loading_type' => $dictionaryItems['loading_type'][0] ?? $this->dictionaryItemFromFlatFields($cargoItem, 'loading_type'),
                'truck_body_type' => $dictionaryItems['truck_body_type'][0] ?? $this->dictionaryItemFromFlatFields($cargoItem, 'truck_body_type'),
                'trailer_type' => $dictionaryItems['trailer_type'][0] ?? $this->dictionaryItemFromFlatFields($cargoItem, 'trailer_type'),
            ];
            $loadingTypeCodes = collect($dictionaryItems['loading_type'])
                ->pluck('code')
                ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
                ->filter()
                ->values()
                ->all();
            $primaryLoadingCode = $this->normalizeLoadingType($primaryDictionaryItems['loading_type']['code'] ?? null);

            $cargoAttributes = [
                'title' => $cargoTitle,
                'description' => $cargoItem['description'] ?? null,
                'weight' => $weightKg,
                'volume' => $volume,
                'cargo_type' => $cargoType,
                'packing_type' => $packType,
                'is_hazardous' => (bool) ($cargoItem['dangerous_goods'] ?? false),
                'hazard_class' => $cargoItem['dangerous_class'] ?? null,
                'hs_code' => $cargoItem['hs_code'] ?? null,
                'needs_temperature' => $cargoType === 'temperature_controlled',
                'needs_hydraulic' => in_array('tail_lift', $loadingTypeCodes, true) || $primaryLoadingCode === 'tail_lift',
                'needs_manipulator' => in_array('crane', $loadingTypeCodes, true) || $primaryLoadingCode === 'crane',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'length' => $length,
                'width' => $width,
                'height' => $height,
            ];

            if (Schema::hasColumn('cargos', 'ati_cargo_name')) {
                $cargoAttributes['ati_cargo_name'] = $cargoTitle;
                $cargoAttributes['weight_value'] = $weightValue;
                $cargoAttributes['weight_unit'] = $weightUnit;
                $cargoAttributes['cargo_type_id'] = $this->normalizeNullableInteger($cargoItem['cargo_type_id'] ?? null);
                $cargoAttributes['cargo_type_label'] = $this->nullIfTrimmedEmpty($cargoItem['cargo_type_label'] ?? null);
                $cargoAttributes['pack_type_id'] = $this->normalizeNullableInteger($cargoItem['pack_type_id'] ?? null);
                $cargoAttributes['pack_type_label'] = $this->nullIfTrimmedEmpty($cargoItem['pack_type_label'] ?? null);
                $cargoAttributes['diameter'] = $this->normalizeNullableFloat($cargoItem['diameter_m'] ?? null);
                $cargoAttributes['is_oversized'] = (bool) ($cargoItem['is_oversized'] ?? $cargoType === 'oversized');
                $cargoAttributes['is_fragile'] = (bool) ($cargoItem['is_fragile'] ?? $cargoType === 'fragile');
                $cargoAttributes['ati_cargo_payload'] = is_array($cargoItem['ati_cargo_payload'] ?? null)
                    ? $cargoItem['ati_cargo_payload']
                    : null;

                foreach (['loading_type', 'truck_body_type', 'trailer_type'] as $dictionaryField) {
                    $idColumn = $dictionaryField.'_id';
                    $codeColumn = $dictionaryField.'_code';
                    $labelColumn = $dictionaryField.'_label';
                    $itemsColumn = $dictionaryField.'_items';
                    $primaryItem = $primaryDictionaryItems[$dictionaryField] ?? null;

                    $cargoAttributes[$idColumn] = $this->normalizeNullableInteger($primaryItem['id'] ?? null);
                    $cargoAttributes[$codeColumn] = $dictionaryField === 'loading_type'
                        ? $primaryLoadingCode
                        : $this->nullIfTrimmedEmpty($primaryItem['code'] ?? null);
                    $cargoAttributes[$labelColumn] = $this->nullIfTrimmedEmpty($primaryItem['label'] ?? null);

                    if (Schema::hasColumn('cargos', $itemsColumn)) {
                        $cargoAttributes[$itemsColumn] = $dictionaryItems[$dictionaryField] !== []
                            ? $dictionaryItems[$dictionaryField]
                            : ($primaryItem !== null ? [$primaryItem] : null);
                    }
                }
            }

            if (Schema::hasColumn('cargos', 'order_id')) {
                $cargoAttributes['order_id'] = $order->id;
            }

            if (Schema::hasColumn('cargos', 'package_count')) {
                $cargoAttributes['package_count'] = $cargoItem['package_count'] ?? null;
            } elseif (Schema::hasColumn('cargos', 'pallet_count') && $packType === 'pallet') {
                $cargoAttributes['pallet_count'] = $cargoItem['package_count'] ?? null;
            }

            $cargo = Cargo::query()->create($cargoAttributes);

            if ($primaryLeg !== null) {
                DB::table('cargo_leg')->insert([
                    'cargo_id' => $cargo->id,
                    'order_leg_id' => $primaryLeg->id,
                    'quantity' => 1,
                    'status' => 'planned',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($hasOrderDocuments) {
            foreach ($validated['documents'] ?? [] as $document) {
                if (($document['flow'] ?? null) === 'print_template_workflow') {
                    continue;
                }

                $storedFile = $this->storeDocumentFile($document['file'] ?? null, $order);
                $metadata = [
                    'party' => $document['party'] ?? 'internal',
                    'flow' => $document['flow'] ?? 'uploaded',
                    'stage' => $document['stage'] ?? null,
                    'requirement_key' => $document['requirement_key'] ?? null,
                ];
                if (is_array($storedFile) && isset($storedFile['storage_driver'])) {
                    $metadata['storage_driver'] = $storedFile['storage_driver'];
                }
                $documentAttributes = [
                    'order_id' => $order->id,
                    'entity_type' => 'order',
                    'entity_id' => $order->id,
                    'type' => $document['type'],
                    'number' => $this->nullIfTrimmedEmpty($document['number'] ?? null),
                    'document_date' => $this->normalizeOrderDocumentDate($document['document_date'] ?? null),
                    'generated_pdf_path' => null,
                    'template_id' => $document['template_id'] ?? null,
                    'status' => $document['status'],
                    'original_name' => $storedFile['original_name'] ?? null,
                    'file_path' => $storedFile['file_path'] ?? null,
                    'file_size' => $storedFile['file_size'] ?? null,
                    'mime_type' => $storedFile['mime_type'] ?? null,
                    'uploaded_by' => $user->id,
                    'metadata' => $metadata,
                ];

                OrderDocument::query()->create($documentAttributes);
            }
        }

        if (Schema::hasTable('financial_terms') && filled($validated['financial_term'] ?? null)) {
            $financialTerm = $validated['financial_term'];
            $contractorsCosts = Arr::get($financialTerm, 'contractors_costs', []);

            // Синхронизируем contractors_costs с performers
            $normalizedContractorsCosts = $this->syncContractorsCostsWithPerformers(
                $contractorsCosts,
                $normalizedPerformers
            );

            $order->refresh();

            $additionalFromOrder = (float) ($order->additional_expenses ?? 0)
                + (float) ($order->insurance ?? 0)
                + (float) ($order->bonus ?? 0);

            $totalCost = collect($normalizedContractorsCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0))
                + $additionalFromOrder;
            $cashToCash = CashToCashMarginCalculator::isCashToCash(
                (string) Arr::get($financialTerm, 'client_payment_form', ''),
                $normalizedContractorsCosts,
            );
            $margin = CashToCashMarginCalculator::margin(
                (float) Arr::get($financialTerm, 'client_price', 0),
                $totalCost,
                (float) Arr::get($financialTerm, 'kpi_percent', 0),
                $cashToCash,
            );

            $orderForSummary = $order->fresh(['legs.routePoints']);
            $clientSchedule = Arr::get($financialTerm, 'client_payment_schedule', []);
            $clientSchedule = is_array($clientSchedule) ? $clientSchedule : [];

            $financialTermAttributes = [
                'order_id' => $order->id,
                'client_price' => Arr::get($financialTerm, 'client_price'),
                'client_currency' => Arr::get($financialTerm, 'client_currency', 'RUB'),
                'contractors_costs' => $normalizedContractorsCosts,
                'total_cost' => $totalCost,
                'margin' => $margin,
                'additional_costs' => [],
            ];

            $manualClientTerms = trim((string) Arr::get($financialTerm, 'client_payment_terms', ''));
            $financialTermAttributes['client_payment_terms'] = $manualClientTerms !== ''
                ? Str::limit($manualClientTerms, 255, '')
                : PaymentScheduleSummaryFormatter::format(
                    $clientSchedule,
                    (float) Arr::get($financialTerm, 'client_price', 0),
                    (string) Arr::get($financialTerm, 'client_currency', 'RUB'),
                    $orderForSummary,
                    [],
                );

            $snapshot = $this->encodePaymentTermsPayload($financialTerm);
            if ($snapshot !== null) {
                $financialTermAttributes['payment_terms_snapshot'] = $snapshot;
            }

            // Удаляем старые financial_terms для этого заказа и создаем новую запись
            FinancialTerm::query()->where('order_id', $order->id)->delete();
            FinancialTerm::query()->create($financialTermAttributes);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return Collection<int, OrderLeg>
     */
    private function syncLegs(Order $order, array $performers)
    {
        // Получаем ID всех плечей заказа перед удалением
        $legIds = OrderLeg::query()->where('order_id', $order->id)->pluck('id');

        if ($legIds->isNotEmpty()) {
            if (Schema::hasTable('leg_costs')) {
                LegCost::query()->whereIn('order_leg_id', $legIds)->delete();
            }

            if (Schema::hasTable('leg_contractor_assignments')) {
                LegContractorAssignment::query()->whereIn('order_leg_id', $legIds)->delete();
            }

            // Только после этого удаляем сами плечи
            $this->deleteOrderLegRows($order);
        }

        $legs = collect($performers)
            ->values()
            ->map(function (array $performer, int $index) use ($order): OrderLeg {
                return OrderLeg::query()->create([
                    'order_id' => $order->id,
                    'sequence' => $index + 1,
                    'type' => 'transport',
                    'description' => $performer['stage'] ?? 'leg_'.($index + 1),
                    'metadata' => [
                        'performer' => $performer,
                    ],
                ]);
            });

        if ($legs->isEmpty()) {
            $legs = collect([
                OrderLeg::query()->create([
                    'order_id' => $order->id,
                    'sequence' => 1,
                    'type' => 'transport',
                    'description' => 'leg_1',
                    'metadata' => [],
                ]),
            ]);
        }

        return $legs;
    }

    private function deleteOrderLegRows(Order $order): void
    {
        $orderId = (int) $order->id;

        DB::unprepared("DELETE FROM order_legs WHERE order_id = {$orderId}");
    }

    private function deleteCargoRowsForOrder(Order $order): void
    {
        $orderId = (int) $order->id;

        DB::unprepared("DELETE FROM cargos WHERE order_id = {$orderId}");
    }

    /**
     * Синхронизирует contractors_costs с performers
     *
     * @param  list<array<string, mixed>>  $contractorsCosts
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function syncContractorsCostsWithPerformers(array $contractorsCosts, array $performers): array
    {
        $performersByStage = collect($performers)
            ->keyBy(fn (array $performer): string => $this->normalizeStageIdentifier((string) ($performer['stage'] ?? 'leg_1')));

        return collect($contractorsCosts)
            ->map(function (array $cost) use ($performersByStage): array {
                $stage = $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1'));
                $performer = $performersByStage->get($stage);

                // Обновляем contractor_id из performers, если он есть
                if ($performer && array_key_exists('contractor_id', $performer)) {
                    $cost['contractor_id'] = $performer['contractor_id'] !== null
                        ? (int) $performer['contractor_id']
                        : null;
                }

                // Нормализуем contractor_id
                if (isset($cost['contractor_id']) && $cost['contractor_id'] !== null) {
                    $cost['contractor_id'] = (int) $cost['contractor_id'];
                }

                return $cost;
            })
            ->all();
    }

    private function logStatusChange(Order $order, ?string $from, string $to, int $userId): void
    {
        if (! Schema::hasTable('order_status_logs')) {
            return;
        }

        OrderStatusLog::query()->create([
            'order_id' => $order->id,
            'status_from' => $from,
            'status_to' => $to,
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncDerivedStatus(Order $order, array $validated, User $user, ?string $previousStatus): void
    {
        if (Schema::hasTable('order_documents')) {
            $order->load('documents');
        } else {
            $order->setRelation('documents', collect());
        }

        $order->loadMissing([
            'legs' => fn ($q) => $q->orderBy('sequence'),
            'legs.routePoints' => fn ($q) => $q->orderBy('sequence'),
        ]);

        $derivedStatus = $this->orderStatusService->resolve($order, $validated['status'] ?? null);

        $order->forceFill([
            'status' => $derivedStatus,
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'is_active' => ! in_array($derivedStatus, ['closed', 'cancelled', 'disruption'], true),
        ])->save();

        if ($previousStatus !== $derivedStatus) {
            $this->logStatusChange($order, $previousStatus, $derivedStatus, $user->id);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingOrderColumns(array $attributes): array
    {
        if ($this->ordersColumnLookup === null) {
            $this->ordersColumnLookup = array_fill_keys(
                Schema::getColumnListing((new Order)->getTable()),
                true
            );
        }

        return array_intersect_key($attributes, $this->ordersColumnLookup);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveOwnCompany(array $validated): ?Contractor
    {
        if (blank($validated['own_company_id'] ?? null)) {
            return null;
        }

        return Contractor::query()->find($validated['own_company_id']);
    }

    /**
     * Пустая строка из формы/JSON для колонки DATE в MySQL недопустима — только null или валидная дата.
     */
    private function normalizeOrderDocumentDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return strlen($trimmed) >= 10 ? substr($trimmed, 0, 10) : $trimmed;
    }

    private function isPrintWorkflowDocument(OrderDocument $document): bool
    {
        if ($document->source === 'print_template') {
            return true;
        }

        return data_get($document->metadata, 'flow') === 'print_template_workflow';
    }

    private function isEmptyPrintWorkflowArtifact(OrderDocument $document): bool
    {
        return $this->isPrintWorkflowDocument($document)
            && blank($document->file_path)
            && blank($document->generated_pdf_path)
            && blank($document->original_name);
    }

    private function nullIfTrimmedEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string|null}>
     */
    private function normalizeDictionaryItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'id' => $this->normalizeNullableInteger($item['id'] ?? null),
                    'code' => $this->nullIfTrimmedEmpty($item['code'] ?? null),
                    'label' => $this->nullIfTrimmedEmpty($item['label'] ?? null),
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== null || $item['code'] !== null || $item['label'] !== null)
            ->unique(fn (array $item): string => (string) ($item['id'] ?? $item['code'] ?? $item['label']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id:int|null, code:string|null, label:string|null}|null
     */
    private function dictionaryItemFromFlatFields(array $payload, string $prefix): ?array
    {
        $item = [
            'id' => $this->normalizeNullableInteger($payload[$prefix.'_id'] ?? null),
            'code' => $this->nullIfTrimmedEmpty($payload[$prefix.'_code'] ?? null),
            'label' => $this->nullIfTrimmedEmpty($payload[$prefix.'_label'] ?? null),
        ];

        return $item['id'] !== null || $item['code'] !== null || $item['label'] !== null ? $item : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}|null
     */
    private function storeDocumentFile(mixed $file, Order $order): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $stored = $this->documentStorage->storeOrderUpload($file, $order->id);

        return [
            'original_name' => $stored['original_name'],
            'file_path' => $stored['file_path'],
            'file_size' => $stored['file_size'],
            'mime_type' => $stored['mime_type'],
            'storage_driver' => $stored['storage_driver'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeValidatedPaymentForms(array $validated): array
    {
        $financialTerm = Arr::get($validated, 'financial_term');
        if (! is_array($financialTerm) || $financialTerm === []) {
            return $validated;
        }

        $validated['financial_term'] = $this->normalizeFinancialTermPaymentForms($financialTerm);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $financialTerm
     * @return array<string, mixed>
     */
    private function normalizeFinancialTermPaymentForms(array $financialTerm): array
    {
        $out = $financialTerm;
        $clientForm = Arr::get($out, 'client_payment_form');
        if (is_string($clientForm) && trim($clientForm) !== '') {
            $normalized = PaymentFormDictionary::normalizeForStorage($clientForm);
            if ($normalized !== null) {
                $out['client_payment_form'] = $normalized;
            }
        }

        $costs = Arr::get($out, 'contractors_costs', []);
        if (! is_array($costs)) {
            return $out;
        }

        foreach ($costs as $i => $cost) {
            if (! is_array($cost)) {
                continue;
            }
            $pf = $cost['payment_form'] ?? null;
            if (is_string($pf) && trim($pf) !== '') {
                $normalized = PaymentFormDictionary::normalizeForStorage($pf);
                if ($normalized !== null) {
                    $out['contractors_costs'][$i]['payment_form'] = $normalized;
                }
            }
        }

        $clientSchedule = $out['client_payment_schedule'] ?? null;
        if (is_array($clientSchedule) && PaymentInstallmentScheduleNormalizer::isInstallmentModel($clientSchedule)) {
            $total = (float) ($out['client_price'] ?? 0);
            $out['client_payment_schedule'] = PaymentInstallmentScheduleNormalizer::normalize($clientSchedule, $total);
        }

        foreach ($costs as $i => $cost) {
            if (! is_array($cost)) {
                continue;
            }
            $sch = $cost['payment_schedule'] ?? null;
            if (is_array($sch) && PaymentInstallmentScheduleNormalizer::isInstallmentModel($sch)) {
                $legTotal = (float) ($cost['amount'] ?? 0);
                $out['contractors_costs'][$i]['payment_schedule'] = PaymentInstallmentScheduleNormalizer::normalize($sch, $legTotal);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $financialTerm
     */
    private function encodePaymentTermsPayload(array $financialTerm): ?string
    {
        $payload = [
            'client' => [
                'payment_form' => Arr::get($financialTerm, 'client_payment_form'),
                'request_mode' => Arr::get($financialTerm, 'client_request_mode', 'single_request'),
                'payment_schedule' => Arr::get($financialTerm, 'client_payment_schedule', []),
                'payment_terms_text' => Arr::get($financialTerm, 'client_payment_terms'),
            ],
            'carriers' => collect(Arr::get($financialTerm, 'contractors_costs', []))
                ->map(fn (array $cost): array => [
                    'stage' => $cost['stage'] ?? null,
                    'contractor_id' => $cost['contractor_id'] !== null ? (int) $cost['contractor_id'] : null,
                    'payment_form' => $cost['payment_form'] ?? null,
                    'payment_schedule' => $cost['payment_schedule'] ?? [],
                    'payment_terms' => $cost['payment_terms'] ?? null,
                ])
                ->values()
                ->all(),
        ];

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, ?string>  $dateContext
     */
    private function formatPaymentScheduleSummary(
        array $schedule,
        float $totalAmount = 0.0,
        string $currency = 'RUB',
        ?Order $order = null,
        array $dateContext = [],
    ): string {
        return PaymentScheduleSummaryFormatter::format($schedule, $totalAmount, $currency, $order, $dateContext);
    }

    /**
     * @param  list<array<string, mixed>>  $contractorCosts
     */
    private function resolveCarrierPaymentTerm(array $contractorCosts): ?string
    {
        return CarrierPaymentTermResolver::fromContractorsCostsArray($contractorCosts);
    }

    /**
     * @return list<string>
     */
    private function normalizeStageIdentifier(?string $stage): string
    {
        $value = trim((string) $stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }

    private function relationsForOrderReload(): array
    {
        $relations = ['client', 'ownCompany', 'legs.routePoints'];

        if (Schema::hasColumn('cargos', 'order_id')) {
            $relations[] = 'cargoItems';
        }

        if (Schema::hasTable('order_documents')) {
            $relations[] = 'documents';
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        if (Schema::hasTable('order_status_logs')) {
            $relations[] = 'statusLogs';
        }

        return $relations;
    }

    /**
     * @return list<string>
     */
    private function relationsForNestedSync(): array
    {
        $relations = ['legs'];

        if (Schema::hasColumn('cargos', 'order_id')) {
            $relations[] = 'cargoItems';
        }

        if (Schema::hasTable('order_documents')) {
            $relations[] = 'documents';
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        return $relations;
    }

    private function deleteExistingCargoItems(Order $order): void
    {
        if (Schema::hasColumn('cargos', 'order_id')) {
            $order->cargoItems()->each(function (Cargo $cargo): void {
                DB::table('cargo_leg')->where('cargo_id', $cargo->id)->delete();
            });

            $this->deleteCargoRowsForOrder($order);

            return;
        }

        $cargoIds = DB::table('cargo_leg')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->where('order_legs.order_id', $order->id)
            ->pluck('cargo_leg.cargo_id');

        if ($cargoIds->isEmpty()) {
            return;
        }

        DB::table('cargo_leg')->whereIn('cargo_id', $cargoIds)->delete();
        Cargo::query()->whereIn('id', $cargoIds)->delete();
    }

    /**
     * Синхронизирует назначения исполнителей и стоимость для плечей
     *
     * @param  Collection<int, OrderLeg>  $legs
     * @param  array<string, mixed>  $validated
     */
    private function syncLegContractorAssignmentsAndCosts(Order $order, Collection $legs, array $validated, User $user): void
    {
        $performers = collect($validated['performers'] ?? [])->values()->all();
        $financialTerm = Arr::get($validated, 'financial_term', []);
        $contractorsCosts = Arr::get($financialTerm, 'contractors_costs', []);

        // Группируем performers и contractors_costs по stage
        $performersByStage = collect($performers)
            ->keyBy(fn (array $performer): string => $this->normalizeStageIdentifier((string) ($performer['stage'] ?? 'leg_1')));

        $costsByStage = collect($contractorsCosts)
            ->keyBy(fn (array $cost): string => $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1')));

        $hasAssignmentsTable = Schema::hasTable('leg_contractor_assignments');
        $hasLegCostsTable = Schema::hasTable('leg_costs');

        foreach ($legs as $leg) {
            $stage = $this->normalizeStageIdentifier($leg->description);
            $performer = $performersByStage->get($stage);
            $cost = $costsByStage->get($stage);
            $resolvedContractorId = null;

            if (is_array($cost) && array_key_exists('contractor_id', $cost) && $cost['contractor_id'] !== null) {
                $resolvedContractorId = (int) $cost['contractor_id'];
            } elseif (is_array($performer) && array_key_exists('contractor_id', $performer) && $performer['contractor_id'] !== null) {
                $resolvedContractorId = (int) $performer['contractor_id'];
            }

            // Удаляем старые записи для этого плеча
            if ($hasAssignmentsTable) {
                LegContractorAssignment::query()->where('order_leg_id', $leg->id)->delete();
            }

            if ($hasLegCostsTable) {
                LegCost::query()->where('order_leg_id', $leg->id)->delete();
            }

            if (! $hasAssignmentsTable && ! $hasLegCostsTable) {
                continue;
            }

            $assignment = null;
            if ($resolvedContractorId !== null) {
                if ($hasAssignmentsTable) {
                    $assignment = LegContractorAssignment::query()->create([
                        'order_leg_id' => $leg->id,
                        'contractor_id' => $resolvedContractorId,
                        'assigned_at' => now(),
                        'assigned_by' => $user->id,
                        'status' => 'confirmed',
                        'notes' => is_array($performer) ? ($performer['notes'] ?? null) : null,
                    ]);
                }

                // Создаем стоимость, если есть данные о стоимости
                if ($cost && $hasLegCostsTable) {
                    LegCost::query()->create([
                        'order_leg_id' => $leg->id,
                        'amount' => $cost['amount'] ?? null,
                        'currency' => $cost['currency'] ?? 'RUB',
                        'payment_form' => $cost['payment_form'] ?? null,
                        'payment_schedule' => $cost['payment_schedule'] ?? null,
                        'status' => 'draft',
                        'calculated_at' => now(),
                        'calculated_by' => $user->id,
                        'leg_contractor_assignment_id' => $assignment?->id,
                    ]);
                }
            } elseif ($cost && $hasLegCostsTable) {
                // Если есть только стоимость без исполнителя
                LegCost::query()->create([
                    'order_leg_id' => $leg->id,
                    'amount' => $cost['amount'] ?? null,
                    'currency' => $cost['currency'] ?? 'RUB',
                    'payment_form' => $cost['payment_form'] ?? null,
                    'payment_schedule' => $cost['payment_schedule'] ?? null,
                    'status' => 'draft',
                    'calculated_at' => now(),
                    'calculated_by' => $user->id,
                ]);
            }
        }
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

    private function normalizeTime(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $raw)) {
            return null;
        }

        return $raw;
    }
}
