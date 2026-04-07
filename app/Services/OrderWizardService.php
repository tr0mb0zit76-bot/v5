<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\OrderStatusLog;
use App\Models\RoutePoint;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class OrderWizardService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderCompensationService $orderCompensationService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): Order
    {
        return DB::transaction(function () use ($validated, $user): Order {
            $ownCompany = $this->resolveOwnCompany($validated);
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany)['company_code'], 'order_number' => $validated['order_number']];

            $order = Order::query()->create($this->extractOrderAttributes($validated, $user, $generatedNumber, true));

            $this->syncNestedData($order, $validated, $user);
            $this->orderCompensationService->recalculateImpactedPeriods($order->fresh());
            $this->syncDerivedStatus($order, $validated, $user, null);

            return $order->load($this->relationsForOrderReload());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Order $order, array $validated, User $user): Order
    {
        return DB::transaction(function () use ($order, $validated, $user): Order {
            $previousStatus = $order->status;
            $previousOrderDate = optional($order->order_date)?->toDateString();
            $previousManagerId = $order->manager_id;

            // Check if deal type changed
            $oldDealType = $this->orderCompensationService->calculateOrder($order)['deal_type'];
            $ownCompany = $this->resolveOwnCompany($validated);
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany)['company_code'], 'order_number' => $validated['order_number']];

            $order->update($this->extractOrderAttributes($validated, $user, $generatedNumber, false));

            $this->syncNestedData($order, $validated, $user);

            $updatedOrder = $order->fresh();
            $newDealType = $this->orderCompensationService->calculateOrder($updatedOrder)['deal_type'];
            $dealTypeChanged = $oldDealType !== $newDealType && $oldDealType !== 'unknown' && $newDealType !== 'unknown';

            $this->orderCompensationService->recalculateImpactedPeriods($updatedOrder, $previousManagerId, $previousOrderDate, $dealTypeChanged);
            $this->syncDerivedStatus($updatedOrder, $validated, $user, $previousStatus);

            return $updatedOrder->load($this->relationsForOrderReload());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{company_code: string, order_number: string}  $numberData
     * @return array<string, mixed>
     */
    private function extractOrderAttributes(array $validated, User $user, array $numberData, bool $isCreating): array
    {
        $performers = collect($validated['performers'] ?? [])->values()->all();
        $routePoints = collect($validated['route_points'] ?? [])->sortBy('sequence')->values();
        $firstLoadingDate = $routePoints->firstWhere('type', 'loading')['planned_date'] ?? null;
        $lastUnloadingDate = $routePoints->where('type', 'unloading')->last()['planned_date'] ?? null;
        $financialTerm = Arr::get($validated, 'financial_term', []);
        $contractorCosts = Arr::get($financialTerm, 'contractors_costs', []);
        $performerTotal = collect($contractorCosts)->sum(fn (array $performer): float => (float) ($performer['amount'] ?? 0));
        $additionalTotal = collect(Arr::get($financialTerm, 'additional_costs', []))
            ->sum(fn (array $item): float => (float) ($item['amount'] ?? 0));
        $clientPrice = (float) Arr::get($financialTerm, 'client_price', 0);
        $bonus = (float) ($validated['bonus'] ?? 0);
        $clientPaymentSchedule = Arr::get($financialTerm, 'client_payment_schedule', []);
        $clientPaymentSummary = $this->formatPaymentScheduleSummary($clientPaymentSchedule);
        $carrierPaymentForm = $this->resolveCarrierPaymentForm($contractorCosts);
        $carrierPaymentSummary = $this->resolveCarrierPaymentTerm($contractorCosts);

        // Нормализуем contractor_id в массиве performers (преобразуем строку в integer)
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
            'manager_id' => $user->id,
            'order_date' => $validated['order_date'],
            'loading_date' => $firstLoadingDate,
            'unloading_date' => $lastUnloadingDate,
            'customer_id' => $validated['client_id'],
            'own_company_id' => $validated['own_company_id'] ?? null,
            'carrier_id' => $carrierId,
            'customer_rate' => $clientPrice ?: null,
            'customer_payment_form' => Arr::get($financialTerm, 'client_payment_form'),
            'customer_payment_term' => $clientPaymentSummary,
            'payment_terms' => $this->encodePaymentTermsPayload($financialTerm),
            'special_notes' => $validated['special_notes'] ?? null,
            'carrier_rate' => $performerTotal ?: null,
            'carrier_payment_form' => $carrierPaymentForm,
            'carrier_payment_term' => $carrierPaymentSummary,
            'additional_expenses' => $additionalTotal,
            'bonus' => $bonus,
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

        return $this->onlyExistingOrderColumns($attributes);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Order $order, array $validated, User $user): void
    {
        $order->loadMissing($this->relationsForNestedSync());

        // Нормализуем performers для передачи в syncLegs
        $performers = collect($validated['performers'] ?? [])->values()->all();
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
        $routePointSequenceByLeg = [];

        $this->deleteExistingCargoItems($order);

        if (Schema::hasTable('order_documents')) {
            $order->documents()->delete();
        }

        if (Schema::hasTable('financial_terms')) {
            $order->financialTerms()->delete();
        }

        foreach ($routePoints as $index => $routePoint) {
            if ($primaryLeg === null) {
                break;
            }

            $routePointStage = $this->normalizeStageIdentifier((string) ($routePoint['stage'] ?? ''));
            $targetLeg = $legsByStage->get($routePointStage, $primaryLeg);
            $normalizedData = Arr::get($routePoint, 'normalized_data', []);
            $legSequence = ($routePointSequenceByLeg[$targetLeg->id] ?? 0) + 1;
            $routePointSequenceByLeg[$targetLeg->id] = $legSequence;

            $routePointAttributes = [
                'order_leg_id' => $targetLeg->id,
                'type' => $routePoint['type'],
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

            if (Schema::hasColumn('route_points', 'address')) {
                $routePointAttributes['address'] = $routePoint['address'];
            }

            if (Schema::hasColumn('route_points', 'normalized_data')) {
                $routePointAttributes['normalized_data'] = $normalizedData;
            } elseif (Schema::hasColumn('route_points', 'metadata')) {
                $routePointAttributes['metadata'] = [
                    'address' => $routePoint['address'],
                    'normalized_data' => $normalizedData,
                ];
            } elseif (Schema::hasColumn('route_points', 'instructions')) {
                $routePointAttributes['instructions'] = $routePoint['address'];
            }

            RoutePoint::query()->create($routePointAttributes);
        }

        foreach ($validated['cargo_items'] ?? [] as $cargoItem) {
            $cargoAttributes = [
                'title' => $cargoItem['name'],
                'description' => $cargoItem['description'] ?? null,
                'weight' => $cargoItem['weight_kg'] ?? null,
                'volume' => $cargoItem['volume_m3'] ?? null,
                'cargo_type' => $cargoItem['cargo_type'],
                'packing_type' => $cargoItem['package_type'] ?? null,
                'is_hazardous' => (bool) ($cargoItem['dangerous_goods'] ?? false),
                'hazard_class' => $cargoItem['dangerous_class'] ?? null,
                'hs_code' => $cargoItem['hs_code'] ?? null,
                'needs_temperature' => $cargoItem['cargo_type'] === 'temperature_controlled',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            if (Schema::hasColumn('cargos', 'order_id')) {
                $cargoAttributes['order_id'] = $order->id;
            }

            if (Schema::hasColumn('cargos', 'package_count')) {
                $cargoAttributes['package_count'] = $cargoItem['package_count'] ?? null;
            } elseif (Schema::hasColumn('cargos', 'pallet_count') && ($cargoItem['package_type'] ?? null) === 'pallet') {
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

        if (Schema::hasTable('order_documents')) {
            foreach ($validated['documents'] ?? [] as $document) {
                $storedFile = $this->storeDocumentFile($document['file'] ?? null);
                $documentAttributes = [
                    'order_id' => $order->id,
                    'type' => $document['type'],
                    'original_name' => $storedFile['original_name'] ?? null,
                    'file_path' => $storedFile['file_path'] ?? null,
                    'file_size' => $storedFile['file_size'] ?? null,
                    'mime_type' => $storedFile['mime_type'] ?? null,
                    'uploaded_by' => $user->id,
                    'metadata' => [
                        'party' => $document['party'] ?? 'internal',
                        'requirement_key' => $document['requirement_key'] ?? null,
                    ],
                ];

                if (Schema::hasColumn('order_documents', 'number')) {
                    $documentAttributes['number'] = $document['number'] ?? null;
                }

                if (Schema::hasColumn('order_documents', 'document_date')) {
                    $documentAttributes['document_date'] = $document['document_date'] ?? null;
                }

                if (Schema::hasColumn('order_documents', 'generated_pdf_path')) {
                    $documentAttributes['generated_pdf_path'] = null;
                }

                if (Schema::hasColumn('order_documents', 'template_id')) {
                    $documentAttributes['template_id'] = $document['template_id'] ?? null;
                }

                if (Schema::hasColumn('order_documents', 'status')) {
                    $documentAttributes['status'] = $document['status'];
                }

                OrderDocument::query()->create($documentAttributes);
            }
        }

        if (Schema::hasTable('financial_terms') && filled($validated['financial_term'] ?? null)) {
            $financialTerm = $validated['financial_term'];
            $contractorsCosts = Arr::get($financialTerm, 'contractors_costs', []);
            $additionalCosts = Arr::get($financialTerm, 'additional_costs', []);

            // Нормализуем contractor_id в contractors_costs
            $normalizedContractorsCosts = collect($contractorsCosts)
                ->map(function (array $cost): array {
                    if (isset($cost['contractor_id']) && $cost['contractor_id'] !== null) {
                        $cost['contractor_id'] = (int) $cost['contractor_id'];
                    }

                    return $cost;
                })
                ->all();

            $totalCost = collect($normalizedContractorsCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0))
                + collect($additionalCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
            $margin = (float) Arr::get($financialTerm, 'client_price', 0) * (1 - ((float) Arr::get($financialTerm, 'kpi_percent', 0) / 100)) - $totalCost;

            $financialTermAttributes = [
                'order_id' => $order->id,
                'client_price' => Arr::get($financialTerm, 'client_price'),
                'client_currency' => Arr::get($financialTerm, 'client_currency', 'RUB'),
                'contractors_costs' => $normalizedContractorsCosts,
                'total_cost' => $totalCost,
                'margin' => $margin,
                'additional_costs' => $additionalCosts,
            ];

            if (Schema::hasColumn('financial_terms', 'client_payment_terms')) {
                $financialTermAttributes['client_payment_terms'] = $this->formatPaymentScheduleSummary(
                    Arr::get($financialTerm, 'client_payment_schedule', [])
                );
            }

            FinancialTerm::query()->create($financialTermAttributes);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return Collection<int, OrderLeg>
     */
    private function syncLegs(Order $order, array $performers)
    {
        // Use a raw query to avoid prepared statement issues with MySQL error 1615
        DB::statement('DELETE FROM order_legs WHERE order_id = ?', [$order->id]);

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

        $derivedStatus = $this->orderStatusService->resolve($order, $validated['status'] ?? null);

        $order->forceFill([
            'status' => $derivedStatus,
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'is_active' => ! in_array($derivedStatus, ['closed', 'cancelled'], true),
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
        return collect($attributes)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn('orders', $column))
            ->all();
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
     * @return array<string, mixed>|null
     */
    private function storeDocumentFile(mixed $file): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = $file->store('order-documents');

        return [
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
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
            ],
            'carriers' => collect(Arr::get($financialTerm, 'contractors_costs', []))
                ->map(fn (array $cost): array => [
                    'stage' => $cost['stage'] ?? null,
                    'contractor_id' => $cost['contractor_id'] !== null ? (int) $cost['contractor_id'] : null,
                    'payment_form' => $cost['payment_form'] ?? null,
                    'payment_schedule' => $cost['payment_schedule'] ?? [],
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
     */
    private function formatPaymentScheduleSummary(array $schedule): ?string
    {
        $postpaymentDays = (int) Arr::get($schedule, 'postpayment_days', 0);
        $postpaymentMode = strtoupper((string) Arr::get($schedule, 'postpayment_mode', 'ottn'));
        $hasPrepayment = (bool) Arr::get($schedule, 'has_prepayment', false);

        if (! $hasPrepayment) {
            return "{$postpaymentDays} дн {$postpaymentMode}";
        }

        $prepaymentRatio = (int) Arr::get($schedule, 'prepayment_ratio', 0);
        $prepaymentDays = (int) Arr::get($schedule, 'prepayment_days', 0);
        $prepaymentMode = strtoupper((string) Arr::get($schedule, 'prepayment_mode', 'fttn'));
        $postpaymentRatio = max(0, 100 - $prepaymentRatio);

        return "{$prepaymentRatio}/{$postpaymentRatio}, {$prepaymentDays} дн {$prepaymentMode} / {$postpaymentDays} дн {$postpaymentMode}";
    }

    /**
     * @param  list<array<string, mixed>>  $contractorCosts
     */
    private function resolveCarrierPaymentForm(array $contractorCosts): ?string
    {
        $forms = collect($contractorCosts)
            ->pluck('payment_form')
            ->filter()
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return null;
        }

        return $forms->count() === 1 ? $forms->first() : 'mixed';
    }

    /**
     * @param  list<array<string, mixed>>  $contractorCosts
     */
    private function resolveCarrierPaymentTerm(array $contractorCosts): ?string
    {
        $summaries = collect($contractorCosts)
            ->map(fn (array $cost): ?string => $this->formatPaymentScheduleSummary((array) ($cost['payment_schedule'] ?? [])))
            ->filter()
            ->unique()
            ->values();

        if ($summaries->isEmpty()) {
            return null;
        }

        return $summaries->count() === 1 ? $summaries->first() : 'см. этапы';
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

            $order->cargoItems()->delete();

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
}
