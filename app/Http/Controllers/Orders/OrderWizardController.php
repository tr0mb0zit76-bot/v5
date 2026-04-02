<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\Order;
use App\Services\DaDataService;
use App\Services\OrderDocumentRequirementService;
use App\Services\OrderWizardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JsonException;
use Inertia\Inertia;
use Inertia\Response;

class OrderWizardController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function store(StoreOrderRequest $request, OrderWizardService $orderWizardService): RedirectResponse
    {
        $order = $orderWizardService->create($request->validated(), $request->user());

        return to_route('orders.edit', $order);
    }

    public function edit(Request $request, Order $order): Response
    {
        return $this->renderPage($request, $order->load([
            'client',
            'ownCompany',
            'cargoItems',
            'documents',
            'financialTerms',
            'statusLogs',
            'legs.routePoints',
        ]));
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderWizardService $orderWizardService): RedirectResponse
    {
        $order = $orderWizardService->update($order, $request->validated(), $request->user());

        return to_route('orders.edit', $order);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->canDeleteOrder($request, $order), 403);

        DB::transaction(function () use ($order): void {
            $order->loadMissing('legs.routePoints', 'cargoItems', 'documents', 'financialTerms', 'statusLogs');

            DB::table('cargo_leg')
                ->whereIn('cargo_id', $order->cargoItems->pluck('id'))
                ->delete();

            DB::table('route_points')
                ->whereIn('order_leg_id', $order->legs->pluck('id'))
                ->delete();

            $order->documents()->delete();
            $order->financialTerms()->delete();
            $order->statusLogs()->delete();
            $order->cargoItems()->delete();
            $order->legs()->delete();
            $order->delete();
        });

        return to_route('orders.index');
    }

    public function suggestAddress(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestAddress($request->string('query')->toString()),
        ]);
    }

    public function storeContractor(StoreInlineOrderContractorRequest $request): JsonResponse
    {
        $contractor = Contractor::query()->create([
            'type' => $request->input('type', 'customer'),
            'name' => $request->string('name')->toString(),
            'inn' => $request->string('inn')->toString() ?: null,
            'kpp' => $request->string('kpp')->toString() ?: null,
            'legal_address' => $request->string('address')->toString() ?: null,
            'actual_address' => $request->string('address')->toString() ?: null,
            'phone' => $request->string('phone')->toString() ?: null,
            'email' => $request->string('email')->toString() ?: null,
            'contact_person' => $request->string('contact_person')->toString() ?: null,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'contractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'type' => $contractor->type,
                'is_own_company' => $contractor->is_own_company,
            ],
        ], 201);
    }

    private function renderPage(Request $request, ?Order $order = null): Response
    {
        $documentRequirementService = app(OrderDocumentRequirementService::class);

        $contractors = Contractor::query()
            ->where('is_active', true)
            ->orderByDesc('is_own_company')
            ->orderBy('name')
            ->get(['id', 'name', 'inn', 'phone', 'email', 'type', 'is_own_company']);

        return Inertia::render('Orders/Wizard', [
            'order' => $order === null ? null : $this->serializeOrder($order),
            'contractors' => $contractors->values(),
            'ownCompanies' => $contractors->where('is_own_company', true)->values(),
            'cargoTypeOptions' => [
                ['value' => 'general', 'label' => 'Общий груз'],
                ['value' => 'dangerous', 'label' => 'Опасный груз'],
                ['value' => 'temperature_controlled', 'label' => 'Температурный режим'],
                ['value' => 'oversized', 'label' => 'Негабаритный груз'],
                ['value' => 'fragile', 'label' => 'Хрупкий груз'],
            ],
            'packageTypeOptions' => [
                ['value' => 'pallet', 'label' => 'Паллета'],
                ['value' => 'box', 'label' => 'Короб'],
                ['value' => 'crate', 'label' => 'Ящик'],
                ['value' => 'roll', 'label' => 'Рулон'],
                ['value' => 'bag', 'label' => 'Мешок'],
            ],
            'currencyOptions' => [
                ['value' => 'RUB', 'label' => 'RUB'],
                ['value' => 'USD', 'label' => 'USD'],
                ['value' => 'CNY', 'label' => 'CNY'],
                ['value' => 'EUR', 'label' => 'EUR'],
            ],
            'documentTypeOptions' => $documentRequirementService->documentTypeOptions(),
            'documentPartyOptions' => $documentRequirementService->partyOptions(),
            'requiredDocumentRules' => $documentRequirementService->requirementRules(),
            'requiredDocumentChecklist' => $documentRequirementService->checklistForOrder($order),
            'orderStatusOptions' => [
                ['value' => 'new', 'label' => 'Новый заказ'],
                ['value' => 'in_progress', 'label' => 'Выполняется'],
                ['value' => 'documents', 'label' => 'Документы'],
                ['value' => 'payment', 'label' => 'Оплата'],
                ['value' => 'closed', 'label' => 'Закрыта'],
                ['value' => 'draft', 'label' => 'Черновик (legacy)'],
                ['value' => 'pending', 'label' => 'На согласовании (legacy)'],
                ['value' => 'confirmed', 'label' => 'Подтвержден (legacy)'],
                ['value' => 'completed', 'label' => 'Завершен (legacy)'],
                ['value' => 'cancelled', 'label' => 'Отменена'],
            ],
            'documentStatusOptions' => [
                ['value' => 'draft', 'label' => 'Черновик'],
                ['value' => 'pending', 'label' => 'Ожидает'],
                ['value' => 'signed', 'label' => 'Подписан'],
                ['value' => 'sent', 'label' => 'Отправлен'],
            ],
            'currentUser' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
            ],
            'cargoTitleSuggestions' => Cargo::query()
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->distinct()
                ->orderBy('title')
                ->limit(30)
                ->pluck('title')
                ->values(),
        ]);
    }

    private function canDeleteOrder(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        return $order->manager_id === $user->id
            && $order->loading_date === null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $financialTerm = $order->financialTerms->first();
        $primaryLeg = $order->legs->sortBy('sequence')->first();
        $paymentTermsConfig = $this->decodePaymentTermsConfig($order->payment_terms);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => optional($order->order_date)?->toDateString(),
            'client_id' => $order->customer_id,
            'own_company_id' => $order->own_company_id,
            'responsible_id' => $order->manager_id,
            'payment_terms' => $order->payment_terms,
            'special_notes' => $order->special_notes,
            'performers' => $order->performers ?? [],
            'route_points' => $primaryLeg?->routePoints->map(fn ($point): array => [
                'id' => $point->id,
                'type' => $point->type,
                'sequence' => $point->sequence,
                'address' => $point->address,
                'normalized_data' => $point->normalized_data ?? [],
                'planned_date' => optional($point->planned_date)?->toDateString(),
                'actual_date' => optional($point->actual_date)?->toDateString(),
                'contact_person' => $point->contact_person,
                'contact_phone' => $point->contact_phone,
            ])->values()->all() ?? [],
            'cargo_items' => $order->cargoItems->map(fn ($cargo): array => [
                'id' => $cargo->id,
                'name' => $cargo->title,
                'description' => $cargo->description,
                'weight_kg' => $cargo->weight,
                'volume_m3' => $cargo->volume,
                'package_type' => $cargo->packing_type,
                'package_count' => $cargo->package_count,
                'dangerous_goods' => $cargo->is_hazardous,
                'dangerous_class' => $cargo->hazard_class,
                'hs_code' => $cargo->hs_code,
                'cargo_type' => $cargo->cargo_type ?: 'general',
            ])->values()->all(),
            'financial_term' => [
                'client_price' => $financialTerm?->client_price,
                'client_currency' => $financialTerm?->client_currency ?? 'RUB',
                'client_payment_form' => $order->customer_payment_form ?? 'vat',
                'client_payment_schedule' => $paymentTermsConfig['client']['payment_schedule'] ?? [],
                'contractors_costs' => collect($financialTerm?->contractors_costs ?? [])
                    ->map(fn ($cost): array => [
                        'stage' => $cost['stage'] ?? null,
                        'contractor_id' => $cost['contractor_id'] ?? null,
                        'amount' => $cost['amount'] ?? null,
                        'currency' => $cost['currency'] ?? 'RUB',
                        'payment_form' => $cost['payment_form'] ?? 'no_vat',
                        'payment_schedule' => $cost['payment_schedule'] ?? [],
                    ])
                    ->values()
                    ->all(),
                'additional_costs' => $financialTerm?->additional_costs ?? [],
                'kpi_percent' => $order->kpi_percent,
            ],
            'documents' => $order->documents->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type,
                'party' => data_get($document->metadata, 'party', 'internal'),
                'requirement_key' => data_get($document->metadata, 'requirement_key'),
                'number' => $document->number,
                'document_date' => optional($document->document_date)?->toDateString(),
                'status' => $document->status,
                'original_name' => $document->original_name,
                'file_path' => $document->file_path,
                'generated_pdf_path' => $document->generated_pdf_path,
                'template_id' => $document->template_id,
            ])->values()->all(),
            'status_logs' => $order->statusLogs->map(fn ($log): array => [
                'id' => $log->id,
                'status_from' => $log->status_from,
                'status_to' => $log->status_to,
                'comment' => $log->comment,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])->values()->all(),
        ];
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
}
