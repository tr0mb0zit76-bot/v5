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
use Illuminate\Support\Facades\DB;
use JsonException;

class OrderWizardService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderStatusService $orderStatusService
    ) {
    }

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
            $this->syncDerivedStatus($order, $validated, $user, null);

            return $order->load(['client', 'ownCompany', 'cargoItems', 'documents', 'financialTerms', 'statusLogs', 'legs.routePoints']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Order $order, array $validated, User $user): Order
    {
        return DB::transaction(function () use ($order, $validated, $user): Order {
            $previousStatus = $order->status;
            $ownCompany = $this->resolveOwnCompany($validated);
            $generatedNumber = blank($validated['order_number'] ?? null)
                ? $this->orderNumberGenerator->generate($ownCompany)
                : ['company_code' => $this->orderNumberGenerator->generate($ownCompany)['company_code'], 'order_number' => $validated['order_number']];

            $order->update($this->extractOrderAttributes($validated, $user, $generatedNumber, false));

            $this->syncNestedData($order, $validated, $user);
            $this->syncDerivedStatus($order, $validated, $user, $previousStatus);

            return $order->load(['client', 'ownCompany', 'cargoItems', 'documents', 'financialTerms', 'statusLogs', 'legs.routePoints']);
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
        $kpiPercent = (float) Arr::get($financialTerm, 'kpi_percent', 0);
        $totalCost = $performerTotal + $additionalTotal;
        $margin = ($clientPrice * (1 - ($kpiPercent / 100))) - $totalCost;
        $clientPaymentSchedule = Arr::get($financialTerm, 'client_payment_schedule', []);
        $clientPaymentSummary = $this->formatPaymentScheduleSummary($clientPaymentSchedule);
        $carrierPaymentForm = $this->resolveCarrierPaymentForm($contractorCosts);
        $carrierPaymentSummary = $this->resolveCarrierPaymentTerm($contractorCosts);

        return [
            'order_number' => $numberData['order_number'],
            'company_code' => $numberData['company_code'],
            'manager_id' => $user->id,
            'order_date' => $validated['order_date'],
            'loading_date' => $firstLoadingDate,
            'unloading_date' => $lastUnloadingDate,
            'customer_id' => $validated['client_id'],
            'own_company_id' => $validated['own_company_id'] ?? null,
            'carrier_id' => collect($performers)->pluck('contractor_id')->filter()->first(),
            'customer_rate' => $clientPrice ?: null,
            'customer_payment_form' => Arr::get($financialTerm, 'client_payment_form'),
            'customer_payment_term' => $clientPaymentSummary,
            'payment_terms' => $this->encodePaymentTermsPayload($financialTerm),
            'special_notes' => $validated['special_notes'] ?? null,
            'carrier_rate' => $performerTotal ?: null,
            'carrier_payment_form' => $carrierPaymentForm,
            'carrier_payment_term' => $carrierPaymentSummary,
            'additional_expenses' => $additionalTotal,
            'kpi_percent' => $kpiPercent ?: null,
            'delta' => $margin,
            'status' => $validated['status'],
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
            'is_active' => true,
            'performers' => $performers,
            'updated_by' => $user->id,
            ...($isCreating ? ['created_by' => $user->id] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Order $order, array $validated, User $user): void
    {
        $order->loadMissing('legs', 'cargoItems', 'documents', 'financialTerms');
        $legs = $this->syncLegs($order, $validated['performers'] ?? []);
        $primaryLeg = $legs->first();

        $order->cargoItems()->each(function (Cargo $cargo): void {
            DB::table('cargo_leg')->where('cargo_id', $cargo->id)->delete();
        });
        $order->cargoItems()->delete();
        $order->documents()->delete();
        $order->financialTerms()->delete();

        foreach ($validated['route_points'] ?? [] as $index => $routePoint) {
            if ($primaryLeg === null) {
                break;
            }

            $normalizedData = Arr::get($routePoint, 'normalized_data', []);

            RoutePoint::query()->create([
                'order_leg_id' => $primaryLeg->id,
                'type' => $routePoint['type'],
                'sequence' => $routePoint['sequence'] ?? ($index + 1),
                'address' => $routePoint['address'],
                'normalized_data' => $normalizedData,
                'kladr_id' => Arr::get($normalizedData, 'kladr_id'),
                'latitude' => Arr::get($normalizedData, 'coordinates.lat'),
                'longitude' => Arr::get($normalizedData, 'coordinates.lng'),
                'planned_date' => $routePoint['planned_date'] ?? null,
                'actual_date' => $routePoint['actual_date'] ?? null,
                'contact_person' => $routePoint['contact_person'] ?? null,
                'contact_phone' => $routePoint['contact_phone'] ?? null,
            ]);
        }

        foreach ($validated['cargo_items'] ?? [] as $cargoItem) {
            $cargo = Cargo::query()->create([
                'order_id' => $order->id,
                'title' => $cargoItem['name'],
                'description' => $cargoItem['description'] ?? null,
                'weight' => $cargoItem['weight_kg'] ?? null,
                'volume' => $cargoItem['volume_m3'] ?? null,
                'cargo_type' => $cargoItem['cargo_type'],
                'packing_type' => $cargoItem['package_type'] ?? null,
                'package_count' => $cargoItem['package_count'] ?? null,
                'is_hazardous' => (bool) ($cargoItem['dangerous_goods'] ?? false),
                'hazard_class' => $cargoItem['dangerous_class'] ?? null,
                'hs_code' => $cargoItem['hs_code'] ?? null,
                'needs_temperature' => $cargoItem['cargo_type'] === 'temperature_controlled',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

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

        foreach ($validated['documents'] ?? [] as $document) {
            $storedFile = $this->storeDocumentFile($document['file'] ?? null);

            OrderDocument::query()->create([
                'order_id' => $order->id,
                'type' => $document['type'],
                'number' => $document['number'] ?? null,
                'document_date' => $document['document_date'] ?? null,
                'original_name' => $storedFile['original_name'] ?? null,
                'file_path' => $storedFile['file_path'] ?? null,
                'file_size' => $storedFile['file_size'] ?? null,
                'mime_type' => $storedFile['mime_type'] ?? null,
                'generated_pdf_path' => null,
                'template_id' => $document['template_id'] ?? null,
                'status' => $document['status'],
                'uploaded_by' => $user->id,
                'metadata' => [
                    'party' => $document['party'] ?? 'internal',
                    'requirement_key' => $document['requirement_key'] ?? null,
                ],
            ]);
        }

        if (filled($validated['financial_term'] ?? null)) {
            $financialTerm = $validated['financial_term'];
            $contractorsCosts = Arr::get($financialTerm, 'contractors_costs', []);
            $additionalCosts = Arr::get($financialTerm, 'additional_costs', []);
            $totalCost = collect($contractorsCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0))
                + collect($additionalCosts)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
            $margin = (float) Arr::get($financialTerm, 'client_price', 0) * (1 - ((float) Arr::get($financialTerm, 'kpi_percent', 0) / 100)) - $totalCost;

            FinancialTerm::query()->create([
                'order_id' => $order->id,
                'client_price' => Arr::get($financialTerm, 'client_price'),
                'client_currency' => Arr::get($financialTerm, 'client_currency', 'RUB'),
                'client_payment_terms' => $this->formatPaymentScheduleSummary(Arr::get($financialTerm, 'client_payment_schedule', [])),
                'contractors_costs' => $contractorsCosts,
                'total_cost' => $totalCost,
                'margin' => $margin,
                'additional_costs' => $additionalCosts,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return \Illuminate\Support\Collection<int, OrderLeg>
     */
    private function syncLegs(Order $order, array $performers)
    {
        $order->legs()->delete();

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
        $order->load('documents');

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
                'payment_schedule' => Arr::get($financialTerm, 'client_payment_schedule', []),
            ],
            'carriers' => collect(Arr::get($financialTerm, 'contractors_costs', []))
                ->map(fn (array $cost): array => [
                    'stage' => $cost['stage'] ?? null,
                    'contractor_id' => $cost['contractor_id'] ?? null,
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
}
