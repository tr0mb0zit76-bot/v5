<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\LegContractorAssignment;
use App\Models\LegCost;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\OrderStatusLog;
use App\Models\PrintFormBasicTerm;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Support\CargoPerformerAllocationBuilder;
use App\Support\CarrierPaymentFormResolver;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CashToCashMarginCalculator;
use App\Support\ContractorCostRowClassification;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderFinancialEditAuthorization;
use App\Support\OrderPersistedId;
use App\Support\OrderRouteMilestoneDateResolver;
use App\Support\OwnFleetCatalog;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentInstallmentPlanner;
use App\Support\PaymentInstallmentScheduleNormalizer;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\PaymentTermsSummaryLimits;
use App\Support\PerformerRouteActualDates;
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
        private readonly FleetTripService $fleetTripService,
        private readonly OrderClosingDocumentsNotificationService $closingDocumentsNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): Order
    {
        return DB::transaction(function () use ($validated, $user): Order {
            $validated = $this->normalizeValidatedPaymentForms($validated);
            $ownCompany = $this->resolveOwnCompany($validated);
            $orderOwnerId = $this->resolveOrderOwnerIdFromValidated($validated, $user, true, null);
            $ownerUser = User::query()->find($orderOwnerId) ?? $user;
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany, $ownerUser)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany, $ownerUser)['company_code'], 'order_number' => $validated['order_number']];

            $order = Order::query()->create($this->extractOrderAttributes($validated, $user, $generatedNumber, true, null));

            $this->syncNestedData($order, $validated, $user);
            $freshOrder = OrderRouteMilestoneDateResolver::syncToOrder($order->fresh() ?? $order);
            $this->orderCompensationService->recalculateImpactedPeriods($freshOrder);
            $this->orderCompensationService->refreshOrderCompensationFields($freshOrder->fresh());
            $this->syncDerivedStatus($freshOrder, $validated, $user, null);

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

            if (! OrderFinancialEditAuthorization::userMayEditFinancialFields($user, $order)) {
                $validated = $this->preservePersistedFinancialPayload($order, $validated);
            }
            $previousStatus = $order->status;
            $previousOrderDate = optional($order->order_date)?->toDateString();
            $previousManagerId = $this->hasOrdersColumn('order_owner_id') && $order->order_owner_id !== null
                ? (int) $order->order_owner_id
                : (int) $order->manager_id;

            $ownCompany = $this->resolveOwnCompany($validated);
            $orderOwnerId = $this->resolveOrderOwnerIdFromValidated($validated, $user, false, $order);
            $ownerUser = User::query()->find($orderOwnerId) ?? $user;
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany, $ownerUser)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany, $ownerUser)['company_code'], 'order_number' => $validated['order_number']];

            $validated = $this->normalizeBasicTermsInValidated($validated, $order);
            $order->update($this->extractOrderAttributes($validated, $user, $generatedNumber, false, $order));

            $this->syncNestedData($order, $validated, $user);

            $updatedOrder = OrderRouteMilestoneDateResolver::syncToOrder($order->fresh() ?? $order);

            $this->orderCompensationService->recalculateImpactedPeriods($updatedOrder, $previousManagerId, $previousOrderDate);
            $this->orderCompensationService->refreshOrderCompensationFields($updatedOrder->fresh());
            $this->syncDerivedStatus($updatedOrder, $validated, $user, $previousStatus);

            $this->orderWizardStateService->persistFromValidated($updatedOrder->fresh(), $validated);

            $freshOrder = $updatedOrder->fresh()->load($this->relationsForOrderReload());
            $this->closingDocumentsNotificationService->maybeNotify($freshOrder);

            return $freshOrder;
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
        ?Order $existingOrder = null,
    ): array {
        $existingMetadata = is_array($existingOrder?->metadata) ? $existingOrder->metadata : null;
        $orderOwnerId = $this->resolveOrderOwnerIdFromValidated($validated, $user, $isCreating, $existingOrder);
        $dispatcherId = $this->resolveDispatcherIdFromValidated($validated, $isCreating, $existingOrder);
        $financialTerm = Arr::get($validated, 'financial_term', []);
        $contractorCosts = Arr::get($financialTerm, 'contractors_costs', []);
        $performers = $this->resolvedPerformers($validated);
        $routePoints = collect(
            PerformerRouteActualDates::applyPerformersToRoutePoints(
                collect($validated['route_points'] ?? [])->sortBy('sequence')->values()->all(),
                $performers,
            ),
        )->sortBy('sequence')->values();
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
            ? Str::limit($manualClientTerms, PaymentTermsSummaryLimits::MAX_LENGTH, '')
            : Str::limit($formattedClientSummary, 2000, '');
        $carrierPaymentForm = CarrierPaymentFormResolver::fromContractorsCostsArray($contractorCosts);
        $carrierPaymentRaw = $this->resolveCarrierPaymentTerm($contractorCosts);
        $carrierPaymentSummary = ($carrierPaymentRaw !== null && $carrierPaymentRaw !== '')
            ? Str::limit($carrierPaymentRaw, 2000, '')
            : null;

        $normalizedPerformers = collect($performers)
            ->map(fn (array $performer): array => $this->normalizePerformerPayload($performer))
            ->all();

        $carrierId = $this->resolvePrimaryCarrierIdFromPerformers($normalizedPerformers);

        $attributes = [
            'order_number' => $numberData['order_number'],
            'company_code' => $numberData['company_code'],
            'manager_id' => $orderOwnerId,
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
            'status' => $validated['status'],
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'is_active' => true,
            'performers' => $normalizedPerformers,
            'updated_by' => $user->id,
            ...($isCreating ? ['created_by' => $user->id] : []),
        ];

        if ($this->hasOrdersColumn('order_owner_id')) {
            $attributes['order_owner_id'] = $orderOwnerId;
        }

        if ($this->hasOrdersColumn('dispatcher_id')) {
            $attributes['dispatcher_id'] = $dispatcherId;
        }

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

        $metadata = $this->syncCompensationSplitMetadata($metadata, $orderOwnerId, $dispatcherId, $validated);

        $attributes['metadata'] = $metadata;

        foreach (['insurance', 'bonus'] as $key) {
            if (! $isCreating && ! array_key_exists($key, $validated)) {
                continue;
            }

            $raw = $validated[$key] ?? null;
            $attributes[$key] = $raw !== null && $raw !== '' ? (float) $raw : 0.0;
        }

        if ($isCreating) {
            $attributes['kpi_percent'] = 0;
            $attributes['delta'] = 0;
            $attributes['salary_accrued'] = 0;
        }

        $additionalCostsRaw = Arr::get($financialTerm, 'additional_costs', []);
        $fallbackServiceDate = filled($validated['additional_expenses_payment_date'] ?? null)
            ? (is_string($validated['additional_expenses_payment_date'])
                ? substr($validated['additional_expenses_payment_date'], 0, 10)
                : (string) $validated['additional_expenses_payment_date'])
            : null;
        $attributes['additional_expenses'] = OrderAdditionalCostNormalizer::sumAmounts(
            OrderAdditionalCostNormalizer::normalizeList(
                is_array($additionalCostsRaw) ? $additionalCostsRaw : [],
                (string) Arr::get($financialTerm, 'client_currency', 'RUB'),
                $fallbackServiceDate,
            ),
        );

        if ($isCreating || array_key_exists('cargo_declared_sum', $validated)) {
            $raw = $validated['cargo_declared_sum'] ?? null;
            $attributes['cargo_declared_sum'] = $raw !== null && $raw !== '' ? (float) $raw : null;
        }

        if ($isCreating || array_key_exists('additional_expenses_payment_date', $validated)) {
            $incurredDate = $validated['additional_expenses_payment_date'] ?? null;
            $attributes['additional_expenses_payment_date'] = filled($incurredDate)
                ? (is_string($incurredDate) ? substr($incurredDate, 0, 10) : (string) $incurredDate)
                : null;
        }

        if ($isCreating || array_key_exists('customer_basic_terms', $validated)) {
            $attributes['customer_basic_terms'] = $validated['customer_basic_terms'] ?? null;
        }

        if ($isCreating || array_key_exists('carrier_basic_terms', $validated)) {
            $attributes['carrier_basic_terms'] = $validated['carrier_basic_terms'] ?? null;
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
        $formPerformers = collect($validated['performers'] ?? [])
            ->filter(fn (mixed $performer): bool => is_array($performer))
            ->values();

        if ($formPerformers->isNotEmpty()) {
            return $formPerformers
                ->map(fn (array $performer): array => $this->normalizePerformerPayload($performer))
                ->all();
        }

        return $this->mergePerformerFleetFields(
            $this->performersForLegSync($validated),
            []
        );
    }

    /**
     * @param  array<string, mixed>  $performer
     * @return array<string, mixed>
     */
    private function normalizePerformerPayload(array $performer): array
    {
        $normalized = $performer;

        if (isset($normalized['contractor_id']) && $normalized['contractor_id'] !== null && $normalized['contractor_id'] !== '') {
            $normalized['contractor_id'] = (int) $normalized['contractor_id'];
        } else {
            $normalized['contractor_id'] = null;
        }

        if (isset($normalized['fleet_vehicle_id']) && $normalized['fleet_vehicle_id'] !== null && $normalized['fleet_vehicle_id'] !== '') {
            $normalized['fleet_vehicle_id'] = (int) $normalized['fleet_vehicle_id'];
        } else {
            $normalized['fleet_vehicle_id'] = null;
        }

        if (isset($normalized['fleet_driver_id']) && $normalized['fleet_driver_id'] !== null && $normalized['fleet_driver_id'] !== '') {
            $normalized['fleet_driver_id'] = (int) $normalized['fleet_driver_id'];
        } else {
            $normalized['fleet_driver_id'] = null;
        }

        $normalized['carrier_mode'] = ($normalized['carrier_mode'] ?? 'single') === 'split' ? 'split' : 'single';
        $normalized['execution_mode'] = OwnFleetCatalog::isOwnFleetExecutionMode(
            isset($normalized['execution_mode']) ? (string) $normalized['execution_mode'] : null,
        )
            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
            : null;
        $normalized['fleet_trip_id'] = isset($normalized['fleet_trip_id']) && $normalized['fleet_trip_id'] !== null && $normalized['fleet_trip_id'] !== ''
            ? (int) $normalized['fleet_trip_id']
            : null;

        if ($normalized['carrier_mode'] === 'split' && is_array($normalized['split_carriers'] ?? null)) {
            $normalized['split_carriers'] = collect($normalized['split_carriers'])
                ->filter(fn (mixed $slot): bool => is_array($slot))
                ->values()
                ->map(function (array $slot, int $index): array {
                    return [
                        'slot' => (int) ($slot['slot'] ?? ($index + 1)),
                        'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null && $slot['contractor_id'] !== ''
                            ? (int) $slot['contractor_id']
                            : null,
                        'contractor_name' => isset($slot['contractor_name']) ? (string) $slot['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null && $slot['fleet_vehicle_id'] !== ''
                            ? (int) $slot['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null && $slot['fleet_driver_id'] !== ''
                            ? (int) $slot['fleet_driver_id']
                            : null,
                        'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode(
                            isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null,
                        )
                            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                            : null,
                        'fleet_trip_id' => isset($slot['fleet_trip_id']) && $slot['fleet_trip_id'] !== null && $slot['fleet_trip_id'] !== ''
                            ? (int) $slot['fleet_trip_id']
                            : null,
                        'loading_actual' => PerformerRouteActualDates::normalizeDate($slot['loading_actual'] ?? null),
                        'unloading_actual' => PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null),
                    ];
                })
                ->all();
            $normalized['contractor_id'] = null;
            $normalized['contractor_name'] = null;
            $normalized['fleet_vehicle_id'] = null;
            $normalized['fleet_driver_id'] = null;
            $normalized['loading_actual'] = null;
            $normalized['unloading_actual'] = null;
        } else {
            $normalized['split_carriers'] = [];
            $normalized['loading_actual'] = PerformerRouteActualDates::normalizeDate($normalized['loading_actual'] ?? null);
            $normalized['unloading_actual'] = PerformerRouteActualDates::normalizeDate($normalized['unloading_actual'] ?? null);
        }

        $normalized['loading_special_conditions'] = $this->nullIfTrimmedEmpty($normalized['loading_special_conditions'] ?? null);
        $normalized['unloading_special_conditions'] = $this->nullIfTrimmedEmpty($normalized['unloading_special_conditions'] ?? null);

        return $normalized;
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
                foreach (['fleet_vehicle_id', 'fleet_driver_id', 'carrier_mode', 'split_carriers', 'contractor_id', 'contractor_name', 'execution_mode', 'fleet_trip_id', 'carrier_portal_submission', 'loading_special_conditions', 'unloading_special_conditions'] as $key) {
                    if (! array_key_exists($key, $extra)) {
                        continue;
                    }

                    if ($key === 'split_carriers') {
                        $p[$key] = is_array($extra[$key]) ? $extra[$key] : [];

                        continue;
                    }

                    if ($key === 'carrier_portal_submission') {
                        $p[$key] = is_array($extra[$key]) ? $extra[$key] : null;

                        continue;
                    }

                    $val = $extra[$key];
                    if (in_array($key, ['fleet_vehicle_id', 'fleet_driver_id', 'contractor_id'], true)) {
                        $p[$key] = $val !== null && $val !== '' ? (int) $val : null;

                        continue;
                    }

                    $p[$key] = $val;
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
        $formPerformers = collect($validated['performers'] ?? [])
            ->filter(fn (mixed $performer): bool => is_array($performer))
            ->values();

        if ($formPerformers->isNotEmpty()) {
            return $formPerformers
                ->map(function (array $performer): array {
                    $id = $performer['contractor_id'] ?? null;

                    if (($performer['carrier_mode'] ?? 'single') === 'split') {
                        $firstWithCarrier = collect($performer['split_carriers'] ?? [])
                            ->first(fn (mixed $slot): bool => is_array($slot)
                                && isset($slot['contractor_id'])
                                && $slot['contractor_id'] !== null
                                && $slot['contractor_id'] !== '');

                        if (is_array($firstWithCarrier)) {
                            $id = $firstWithCarrier['contractor_id'];
                        }
                    }

                    return [
                        'stage' => (string) ($performer['stage'] ?? 'leg_1'),
                        'contractor_id' => $id !== null && $id !== '' ? (int) $id : null,
                    ];
                })
                ->all();
        }

        $financialTerm = Arr::get($validated, 'financial_term', []);
        $costs = Arr::get($financialTerm, 'contractors_costs', []);

        if (is_array($costs) && $costs !== []) {
            return collect($costs)
                ->filter(fn (array $cost): bool => ! ContractorCostRowClassification::isAdditional($cost))
                ->unique(fn (array $cost): string => $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1')))
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

        return [['stage' => 'leg_1', 'contractor_id' => null]];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Order $order, array $validated, User $user): void
    {
        $orderId = OrderPersistedId::resolveOrFail($order);
        $hasOrderDocuments = Schema::hasTable('order_documents');

        $order->loadMissing($this->relationsForNestedSync());

        $normalizedPerformers = collect($this->resolvedPerformers($validated))
            ->map(fn (array $performer): array => $this->normalizePerformerPayload($performer))
            ->all();

        $legs = $this->syncLegs($order, $normalizedPerformers);
        $primaryLeg = $legs->first();
        $legsByStage = $legs->keyBy(fn (OrderLeg $leg): string => $this->normalizeStageIdentifier($leg->description));
        $routePoints = collect(
            PerformerRouteActualDates::applyPerformersToRoutePoints(
                collect($validated['route_points'] ?? [])->sortBy('sequence')->values()->all(),
                $normalizedPerformers,
            ),
        )->sortBy('sequence')->values();
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
            $this->purgeEmptyPrintWorkflowArtifacts($order);
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
                $atiPayload = $this->normalizeAtiCargoPayloadArray($cargoItem['ati_cargo_payload'] ?? null);
                $performerAllocations = CargoPerformerAllocationBuilder::resolveForCargoItem(
                    $cargoItem,
                    $normalizedPerformers,
                );
                if ($performerAllocations !== []) {
                    $atiPayload['performer_allocations'] = $performerAllocations;
                } else {
                    unset($atiPayload['performer_allocations']);
                }
                $cargoAttributes['ati_cargo_payload'] = $atiPayload !== [] ? $atiPayload : null;

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
                $cargoAttributes['order_id'] = $orderId;
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
            $this->syncSignedRegistryDocuments($order, $validated['documents'] ?? [], $user);
        }

        if (Schema::hasTable('financial_terms') && filled($validated['financial_term'] ?? null)) {
            $financialTerm = $validated['financial_term'];
            $contractorsCosts = Arr::get($financialTerm, 'contractors_costs', []);

            // Синхронизируем contractors_costs с performers
            $normalizedContractorsCosts = $this->syncContractorsCostsWithPerformers(
                $contractorsCosts,
                $normalizedPerformers
            );

            [$normalizedContractorsCosts, $legacyAdditionalCosts] = OrderAdditionalCostNormalizer::partitionContractorsCosts(
                $normalizedContractorsCosts,
                (string) Arr::get($financialTerm, 'client_currency', 'RUB'),
                filled($validated['additional_expenses_payment_date'] ?? null)
                    ? substr((string) $validated['additional_expenses_payment_date'], 0, 10)
                    : null,
            );

            $normalizedAdditionalCosts = OrderAdditionalCostNormalizer::normalizeList(
                array_merge(
                    is_array(Arr::get($financialTerm, 'additional_costs')) ? Arr::get($financialTerm, 'additional_costs') : [],
                    $legacyAdditionalCosts,
                ),
                (string) Arr::get($financialTerm, 'client_currency', 'RUB'),
                filled($validated['additional_expenses_payment_date'] ?? null)
                    ? substr((string) $validated['additional_expenses_payment_date'], 0, 10)
                    : null,
            );

            $orderForCosts = Order::query()->find($orderId);
            $additionalFromOrder = OrderAdditionalCostNormalizer::sumAmounts($normalizedAdditionalCosts)
                + (float) ($orderForCosts?->insurance ?? 0)
                + (float) ($orderForCosts?->bonus ?? 0);

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

            $orderForSummary = Order::query()
                ->with(['legs.routePoints'])
                ->find($orderId);
            $clientSchedule = Arr::get($financialTerm, 'client_payment_schedule', []);
            $clientSchedule = is_array($clientSchedule) ? $clientSchedule : [];

            $financialTermAttributes = [
                'client_price' => Arr::get($financialTerm, 'client_price'),
                'client_currency' => Arr::get($financialTerm, 'client_currency', 'RUB'),
                'contractors_costs' => $normalizedContractorsCosts,
                'total_cost' => $totalCost,
                'margin' => $margin,
                'additional_costs' => $normalizedAdditionalCosts,
            ];

            $manualClientTerms = trim((string) Arr::get($financialTerm, 'client_payment_terms', ''));
            $financialTermAttributes['client_payment_terms'] = $manualClientTerms !== ''
                ? Str::limit($manualClientTerms, PaymentTermsSummaryLimits::MAX_LENGTH, '')
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

            $financialTermAttributes = $this->onlyExistingFinancialTermColumns($financialTermAttributes);

            $persistedOrder = Order::query()->findOrFail($orderId);
            $persistedOrder->financialTerms()->delete();
            $persistedOrder->financialTerms()->create($financialTermAttributes);

            if ($snapshot !== null && Schema::hasColumn('orders', 'payment_terms')) {
                $persistedOrder->forceFill(['payment_terms' => $snapshot])->saveQuietly();
            }

            $this->fleetTripService->syncPlannedTripsFromOrder(
                $persistedOrder->fresh(),
                $normalizedPerformers,
                $normalizedContractorsCosts,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingFinancialTermColumns(array $attributes): array
    {
        static $columnLookup = null;

        if ($columnLookup === null) {
            if (! Schema::hasTable('financial_terms')) {
                $columnLookup = [];

                return [];
            }

            $columnLookup = array_fill_keys(
                Schema::getColumnListing('financial_terms'),
                true,
            );
        }

        return array_intersect_key($attributes, $columnLookup);
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
        DB::table('order_legs')->where('order_id', $order->id)->delete();
    }

    private function deleteCargoRowsForOrder(Order $order): void
    {
        DB::table('cargos')->where('order_id', $order->id)->delete();
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
                if (ContractorCostRowClassification::isAdditional($cost)) {
                    return $cost;
                }

                $stage = $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1'));
                $performer = $performersByStage->get($stage);
                $carrierSlot = isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                    ? (int) $cost['carrier_slot']
                    : null;

                if (is_array($performer)) {
                    $resolvedContractorId = $this->resolveContractorIdForPerformerSlot($performer, $carrierSlot);
                    $executionMode = OwnFleetCatalog::resolveExecutionModeForSlot($performer, $carrierSlot);

                    if ($resolvedContractorId !== null) {
                        $cost['contractor_id'] = $resolvedContractorId;
                    } elseif (($performer['carrier_mode'] ?? 'single') === 'split' && $carrierSlot !== null) {
                        $cost['contractor_id'] = null;
                    } elseif (array_key_exists('contractor_id', $performer)) {
                        $cost['contractor_id'] = $performer['contractor_id'] !== null
                            ? (int) $performer['contractor_id']
                            : null;
                    }

                    $cost['execution_mode'] = OwnFleetCatalog::isOwnFleetExecutionMode($executionMode)
                        ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                        : null;
                }

                if (isset($cost['contractor_id']) && $cost['contractor_id'] !== null) {
                    $cost['contractor_id'] = (int) $cost['contractor_id'];
                }

                if ($carrierSlot !== null) {
                    $cost['carrier_slot'] = $carrierSlot;
                }

                return $cost;
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     */
    private function resolvePrimaryCarrierIdFromPerformers(array $performers): ?int
    {
        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (is_array($slot) && isset($slot['contractor_id']) && $slot['contractor_id'] !== null && $slot['contractor_id'] !== '') {
                        return (int) $slot['contractor_id'];
                    }
                }

                continue;
            }

            if (isset($performer['contractor_id']) && $performer['contractor_id'] !== null && $performer['contractor_id'] !== '') {
                return (int) $performer['contractor_id'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $performer
     */
    private function resolveContractorIdForPerformerSlot(array $performer, ?int $carrierSlot): ?int
    {
        if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
            if ($carrierSlot === null) {
                return null;
            }

            $slotRow = collect($performer['split_carriers'])
                ->first(fn (mixed $row): bool => is_array($row) && (int) ($row['slot'] ?? 0) === $carrierSlot);

            if (! is_array($slotRow) || ! isset($slotRow['contractor_id']) || $slotRow['contractor_id'] === null || $slotRow['contractor_id'] === '') {
                return null;
            }

            return (int) $slotRow['contractor_id'];
        }

        if (! isset($performer['contractor_id']) || $performer['contractor_id'] === null || $performer['contractor_id'] === '') {
            return null;
        }

        return (int) $performer['contractor_id'];
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

    private function hasOrdersColumn(string $column): bool
    {
        if ($this->ordersColumnLookup === null) {
            $this->ordersColumnLookup = array_fill_keys(
                Schema::getColumnListing((new Order)->getTable()),
                true
            );
        }

        return isset($this->ordersColumnLookup[$column]);
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

    private function purgeEmptyPrintWorkflowArtifacts(Order $order): void
    {
        $order->documents()
            ->get()
            ->each(function (OrderDocument $document): void {
                if ($this->isEmptyPrintWorkflowArtifact($document)) {
                    $document->delete();
                }
            });
    }

    /**
     * Синхронизация подписанных загружаемых документов из вкладки «Документы» (без печатного workflow).
     *
     * @param  list<array<string, mixed>>  $documents
     */
    private function syncSignedRegistryDocuments(Order $order, array $documents, User $user): void
    {
        $incoming = collect($documents)->filter(function (array $document): bool {
            if (($document['flow'] ?? null) === 'print_template_workflow') {
                return false;
            }

            return ($document['status'] ?? '') === 'signed';
        });

        $incomingIds = $incoming
            ->pluck('id')
            ->filter(fn (mixed $id): bool => $id !== null && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $order->documents()
            ->get()
            ->each(function (OrderDocument $document) use ($incomingIds): void {
                if ($this->isPrintWorkflowDocument($document)) {
                    return;
                }

                if (! in_array((int) $document->id, $incomingIds, true)) {
                    $this->deleteUploadedDocumentFile($document);
                    $document->delete();
                }
            });

        foreach ($incoming as $document) {
            $id = isset($document['id']) ? (int) $document['id'] : 0;
            $storedFile = $this->storeDocumentFile($document['file'] ?? null, $order);

            $stage = filled($document['stage'] ?? null)
                ? trim((string) $document['stage'])
                : null;
            $carrierContractorId = $this->resolveCarrierContractorIdForDocumentStage($order, $stage, $document);

            $metadata = [
                'party' => $document['party'] ?? 'internal',
                'flow' => 'uploaded',
                'stage' => $stage,
                'requirement_key' => $document['requirement_key'] ?? null,
            ];

            if ($stage !== null) {
                $metadata['order_leg_stage'] = $stage;
            }

            if ($carrierContractorId !== null) {
                $metadata['carrier_contractor_id'] = $carrierContractorId;
            }

            $contractorId = isset($document['contractor_id']) && (int) $document['contractor_id'] > 0
                ? (int) $document['contractor_id']
                : null;

            if ($contractorId !== null) {
                $metadata['contractor_id'] = $contractorId;
            }

            if (filled($document['requirement_slot_key'] ?? null)) {
                $metadata['requirement_slot_key'] = trim((string) $document['requirement_slot_key']);
            }

            $attributes = [
                'type' => $document['type'],
                'number' => $this->nullIfTrimmedEmpty($document['number'] ?? null),
                'document_date' => $this->normalizeOrderDocumentDate($document['document_date'] ?? null),
                'template_id' => $document['template_id'] ?? null,
                'status' => 'signed',
                'uploaded_by' => $user->id,
            ];

            if ($id > 0) {
                $existing = OrderDocument::query()
                    ->where('order_id', $order->id)
                    ->whereKey($id)
                    ->first();

                if ($existing === null || $this->isPrintWorkflowDocument($existing)) {
                    continue;
                }

                if (is_array($storedFile)) {
                    $this->deleteUploadedDocumentFile($existing);
                    $metadata['storage_driver'] = $storedFile['storage_driver'];
                    $existing->update([
                        ...$attributes,
                        'original_name' => $storedFile['original_name'],
                        'file_path' => $storedFile['file_path'],
                        'file_size' => $storedFile['file_size'],
                        'mime_type' => $storedFile['mime_type'],
                        'metadata' => $metadata,
                    ]);
                } else {
                    $mergedMetadata = array_merge((array) ($existing->metadata ?? []), $metadata);
                    $existing->update([
                        ...$attributes,
                        'metadata' => $mergedMetadata,
                    ]);
                }

                continue;
            }

            if (! is_array($storedFile)) {
                continue;
            }

            $metadata['storage_driver'] = $storedFile['storage_driver'];

            OrderDocument::query()->create([
                'order_id' => $order->id,
                'entity_type' => 'order',
                'entity_id' => $order->id,
                ...$attributes,
                'original_name' => $storedFile['original_name'],
                'file_path' => $storedFile['file_path'],
                'file_size' => $storedFile['file_size'],
                'mime_type' => $storedFile['mime_type'],
                'metadata' => $metadata,
                'generated_pdf_path' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function resolveCarrierContractorIdForDocumentStage(Order $order, ?string $stage, array $document): ?int
    {
        if (isset($document['carrier_contractor_id']) && (int) $document['carrier_contractor_id'] > 0) {
            return (int) $document['carrier_contractor_id'];
        }

        if ($stage === null || $stage === '') {
            return null;
        }

        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $performerStage = trim((string) ($performer['stage'] ?? ''));
            if ($performerStage !== $stage) {
                continue;
            }

            if (isset($performer['contractor_id']) && (int) $performer['contractor_id'] > 0) {
                return (int) $performer['contractor_id'];
            }
        }

        return null;
    }

    private function deleteUploadedDocumentFile(OrderDocument $document): void
    {
        if ($this->isPrintWorkflowDocument($document)) {
            return;
        }

        $path = $document->file_path;
        if (! filled($path)) {
            return;
        }

        $driver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);

        $this->documentStorage->delete(
            (string) $path,
            $driver === DocumentStorageService::DRIVER_NEXTCLOUD
                ? DocumentStorageService::DRIVER_NEXTCLOUD
                : DocumentStorageService::DRIVER_LOCAL,
        );
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
                    'carrier_slot' => isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                        ? (int) $cost['carrier_slot']
                        : null,
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
    /**
     * JSON-колонка иногда приходит/хранится как `[]` (list), а не объект с ключами.
     *
     * @return array<string, mixed>
     */
    private function normalizeAtiCargoPayloadArray(mixed $payload): array
    {
        if (! is_array($payload) || $payload === [] || array_is_list($payload)) {
            return [];
        }

        return $payload;
    }

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

        $costsByStageSlot = collect($contractorsCosts)
            ->filter(fn (array $cost): bool => ! ContractorCostRowClassification::isAdditional($cost))
            ->keyBy(fn (array $cost): string => $this->contractorCostLookupKey(
                (string) ($cost['stage'] ?? 'leg_1'),
                $cost['carrier_slot'] ?? null,
            ));

        $hasAssignmentsTable = Schema::hasTable('leg_contractor_assignments');
        $hasLegCostsTable = Schema::hasTable('leg_costs');

        foreach ($legs as $leg) {
            $stage = $this->normalizeStageIdentifier($leg->description);
            $performer = $performersByStage->get($stage);
            $carrierRows = $this->carrierRowsForLegAssignment($performer, $costsByStageSlot, $stage);

            if ($hasAssignmentsTable) {
                LegContractorAssignment::query()->where('order_leg_id', $leg->id)->delete();
            }

            if ($hasLegCostsTable) {
                LegCost::query()->where('order_leg_id', $leg->id)->delete();
            }

            if (! $hasAssignmentsTable && ! $hasLegCostsTable) {
                continue;
            }

            $primaryAssignment = null;
            $aggregatedLegCost = null;

            foreach ($carrierRows as $carrierRow) {
                $resolvedContractorId = $carrierRow['contractor_id'];
                $cost = $carrierRow['cost'];
                $carrierSlot = (int) ($carrierRow['carrier_slot'] ?? 1);

                if ($resolvedContractorId !== null && $hasAssignmentsTable) {
                    $assignmentAttributes = [
                        'order_leg_id' => $leg->id,
                        'contractor_id' => $resolvedContractorId,
                        'assigned_at' => now(),
                        'assigned_by' => $user->id,
                        'status' => 'confirmed',
                        'notes' => is_array($performer) ? ($performer['notes'] ?? null) : null,
                    ];

                    if (Schema::hasColumn('leg_contractor_assignments', 'carrier_slot')) {
                        $assignmentAttributes['carrier_slot'] = $carrierSlot;
                    }

                    $createdAssignment = LegContractorAssignment::query()->create($assignmentAttributes);

                    if ($primaryAssignment === null) {
                        $primaryAssignment = $createdAssignment;
                    }
                }

                if ($cost) {
                    $aggregatedLegCost = [
                        'amount' => (float) ($aggregatedLegCost['amount'] ?? 0) + (float) ($cost['amount'] ?? 0),
                        'currency' => (string) ($cost['currency'] ?? $aggregatedLegCost['currency'] ?? 'RUB'),
                        'payment_form' => $cost['payment_form'] ?? $aggregatedLegCost['payment_form'] ?? null,
                        'payment_schedule' => $cost['payment_schedule'] ?? $aggregatedLegCost['payment_schedule'] ?? null,
                    ];
                }
            }

            if ($aggregatedLegCost && $hasLegCostsTable) {
                LegCost::query()->create([
                    'order_leg_id' => $leg->id,
                    'amount' => $aggregatedLegCost['amount'] > 0 ? $aggregatedLegCost['amount'] : null,
                    'currency' => $aggregatedLegCost['currency'],
                    'payment_form' => $aggregatedLegCost['payment_form'],
                    'payment_schedule' => $aggregatedLegCost['payment_schedule'],
                    'status' => 'draft',
                    'calculated_at' => now(),
                    'calculated_by' => $user->id,
                    'leg_contractor_assignment_id' => $primaryAssignment?->id,
                ]);
            }
        }
    }

    private function contractorCostLookupKey(string $stage, mixed $carrierSlot): string
    {
        $normalizedStage = $this->normalizeStageIdentifier($stage);
        $slot = $carrierSlot !== null && $carrierSlot !== '' ? (int) $carrierSlot : 0;

        return "{$normalizedStage}#{$slot}";
    }

    /**
     * @param  array<string, mixed>|null  $performer
     * @param  Collection<string, array<string, mixed>>  $costsByStageSlot
     * @return list<array{carrier_slot: int, contractor_id: int|null, cost: array<string, mixed>|null}>
     */
    private function carrierRowsForLegAssignment(?array $performer, $costsByStageSlot, string $stage): array
    {
        if (! is_array($performer)) {
            $fallbackCost = $costsByStageSlot->get($this->contractorCostLookupKey($stage, null));

            return [[
                'carrier_slot' => 1,
                'contractor_id' => isset($fallbackCost['contractor_id']) && $fallbackCost['contractor_id'] !== null
                    ? (int) $fallbackCost['contractor_id']
                    : null,
                'cost' => is_array($fallbackCost) ? $fallbackCost : null,
            ]];
        }

        if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
            return collect($performer['split_carriers'])
                ->filter(fn (mixed $slot): bool => is_array($slot))
                ->map(function (array $slot) use ($costsByStageSlot, $stage): array {
                    $carrierSlot = (int) ($slot['slot'] ?? 1);
                    $cost = $costsByStageSlot->get($this->contractorCostLookupKey($stage, $carrierSlot));
                    $contractorId = $slot['contractor_id'] ?? null;

                    if ($contractorId === null && is_array($cost) && array_key_exists('contractor_id', $cost) && $cost['contractor_id'] !== null) {
                        $contractorId = (int) $cost['contractor_id'];
                    }

                    return [
                        'carrier_slot' => $carrierSlot,
                        'contractor_id' => $contractorId !== null && $contractorId !== '' ? (int) $contractorId : null,
                        'cost' => is_array($cost) ? $cost : null,
                    ];
                })
                ->values()
                ->all();
        }

        $cost = $costsByStageSlot->get($this->contractorCostLookupKey($stage, null));
        $contractorId = $performer['contractor_id'] ?? null;

        if ($contractorId === null && is_array($cost) && array_key_exists('contractor_id', $cost) && $cost['contractor_id'] !== null) {
            $contractorId = (int) $cost['contractor_id'];
        }

        return [[
            'carrier_slot' => 1,
            'contractor_id' => $contractorId !== null && $contractorId !== '' ? (int) $contractorId : null,
            'cost' => is_array($cost) ? $cost : null,
        ]];
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeBasicTermsInValidated(array $validated, Order $order): array
    {
        if (! $this->hasOrdersColumn('customer_basic_terms') || ! $this->hasOrdersColumn('carrier_basic_terms')) {
            return $validated;
        }

        /** @var PrintFormBasicTermsService $service */
        $service = app(PrintFormBasicTermsService::class);

        if (! $service->tablesReady()) {
            return $validated;
        }

        if (array_key_exists('customer_basic_terms', $validated)) {
            $validated['customer_basic_terms'] = $service->normalizeOrderOverride(
                is_array($validated['customer_basic_terms'] ?? null) ? $validated['customer_basic_terms'] : null,
                $order,
                PrintFormBasicTerm::PARTY_CUSTOMER,
            );
        }

        if (array_key_exists('carrier_basic_terms', $validated)) {
            $validated['carrier_basic_terms'] = $service->normalizeOrderOverride(
                is_array($validated['carrier_basic_terms'] ?? null) ? $validated['carrier_basic_terms'] : null,
                $order,
                PrintFormBasicTerm::PARTY_CARRIER,
            );
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function preservePersistedFinancialPayload(Order $order, array $validated): array
    {
        $order->loadMissing('financialTerms');

        $wizardState = is_array($order->wizard_state) ? $order->wizard_state : [];
        $wizardFinancial = is_array($wizardState['financial_term'] ?? null) ? $wizardState['financial_term'] : null;

        if ($wizardFinancial !== null && $wizardFinancial !== []) {
            $validated['financial_term'] = $wizardFinancial;
        } elseif ($order->financialTerms->isNotEmpty()) {
            $financialTerm = $order->financialTerms->first();
            $validated['financial_term'] = [
                'client_price' => $financialTerm->client_price,
                'client_currency' => $financialTerm->client_currency ?? 'RUB',
                'client_payment_form' => $order->customer_payment_form,
                'client_payment_terms' => $financialTerm->client_payment_terms,
                'contractors_costs' => is_array($financialTerm->contractors_costs) ? $financialTerm->contractors_costs : [],
                'additional_costs' => is_array($financialTerm->additional_costs) ? $financialTerm->additional_costs : [],
                'kpi_percent' => $order->kpi_percent,
            ];
        }

        if (array_key_exists('insurance', $validated)) {
            $validated['insurance'] = $order->insurance;
        }

        if (array_key_exists('bonus', $validated)) {
            $validated['bonus'] = $order->bonus;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveOrderOwnerIdFromValidated(array $validated, User $user, bool $isCreating, ?Order $existingOrder): int
    {
        $fromPayload = $validated['order_owner_id'] ?? $validated['responsible_id'] ?? null;
        if ($fromPayload !== null && (int) $fromPayload > 0) {
            return (int) $fromPayload;
        }

        if (! $isCreating && $existingOrder !== null) {
            if ($this->hasOrdersColumn('order_owner_id') && $existingOrder->order_owner_id !== null) {
                return (int) $existingOrder->order_owner_id;
            }

            return (int) $existingOrder->manager_id;
        }

        return (int) $user->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveDispatcherIdFromValidated(array $validated, bool $isCreating, ?Order $existingOrder): ?int
    {
        if (array_key_exists('dispatcher_id', $validated)) {
            $dispatcherId = $validated['dispatcher_id'];

            return $dispatcherId === null || $dispatcherId === '' ? null : (int) $dispatcherId;
        }

        if (! $isCreating && $existingOrder !== null && $this->hasOrdersColumn('dispatcher_id')) {
            return $existingOrder->dispatcher_id !== null ? (int) $existingOrder->dispatcher_id : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function syncCompensationSplitMetadata(array $metadata, int $orderOwnerId, ?int $dispatcherId, array $validated = []): array
    {
        $existing = is_array($metadata['compensation_split'] ?? null) ? $metadata['compensation_split'] : [];

        if ($dispatcherId === null) {
            $metadata['compensation_split'] = [
                'order_owner_id' => $orderOwnerId,
                'dispatcher_id' => null,
                'order_owner_percent' => 100.0,
                'dispatcher_percent' => 0.0,
            ];

            return $metadata;
        }

        $ownerPercent = array_key_exists('compensation_owner_percent', $validated)
            ? (float) $validated['compensation_owner_percent']
            : (float) ($existing['order_owner_percent'] ?? 100);
        $dispatcherPercent = array_key_exists('compensation_dispatcher_percent', $validated)
            ? (float) $validated['compensation_dispatcher_percent']
            : (float) ($existing['dispatcher_percent'] ?? 0);

        $metadata['compensation_split'] = [
            'order_owner_id' => $orderOwnerId,
            'dispatcher_id' => $dispatcherId,
            'order_owner_percent' => round($ownerPercent, 2),
            'dispatcher_percent' => round($dispatcherPercent, 2),
        ];

        return $metadata;
    }
}
