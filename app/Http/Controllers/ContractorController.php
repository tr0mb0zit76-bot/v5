<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractorRequest;
use App\Http\Requests\UpdateContractorRequest;
use App\Models\Contractor;
use App\Services\DaDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function create(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function store(StoreContractorRequest $request): RedirectResponse
    {
        $contractor = DB::transaction(function () use ($request): Contractor {
            $validated = $request->validated();

            $contractor = Contractor::query()->create([
                ...$this->extractContractorAttributes($validated),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);

            return $contractor;
        });

        return to_route('contractors.show', $contractor);
    }

    public function show(Request $request, Contractor $contractor): Response
    {
        return $this->renderPage($request, $contractor);
    }

    public function edit(Request $request, Contractor $contractor): Response
    {
        return $this->renderPage($request, $contractor);
    }

    public function update(UpdateContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        DB::transaction(function () use ($request, $contractor): void {
            $validated = $request->validated();

            $contractor->update([
                ...$this->extractContractorAttributes($validated),
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);
        });

        return to_route('contractors.show', $contractor);
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        abort_if(
            $contractor->customerOrders()->exists() || $contractor->carrierOrders()->exists(),
            422,
            'Нельзя удалить контрагента, связанного с заказами.'
        );

        $contractor->delete();

        return to_route('contractors.index');
    }

    public function suggestParty(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestParty($request->string('query')->toString()),
        ]);
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

    private function renderPage(Request $request, ?Contractor $selectedContractor = null): Response
    {
        $contractors = Contractor::query()
            ->withCount(['contacts', 'customerOrders', 'carrierOrders'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'type' => $contractor->type,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'is_active' => $contractor->is_active,
                'is_own_company' => $contractor->is_own_company,
                'contacts_count' => $contractor->contacts_count,
                'orders_count' => $contractor->customer_orders_count + $contractor->carrier_orders_count,
            ])
            ->values();

        $contractorDetails = null;

        if ($selectedContractor !== null) {
            $selectedContractor->load(['contacts', 'interactions.author:id,name', 'documents']);

            $orders = DB::table('orders')
                ->select('id', 'order_number', 'status', 'order_date', 'customer_rate', 'carrier_rate', 'customer_id', 'carrier_id')
                ->where(function ($query) use ($selectedContractor) {
                    $query->where('customer_id', $selectedContractor->id)
                        ->orWhere('carrier_id', $selectedContractor->id);
                })
                ->orderByDesc('order_date')
                ->limit(20)
                ->get()
                ->map(fn (object $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'customer_rate' => $order->customer_rate,
                    'carrier_rate' => $order->carrier_rate,
                    'relation' => (int) $order->customer_id === $selectedContractor->id ? 'customer' : 'carrier',
                ])
                ->values();

            $contractorDetails = [
                ...$selectedContractor->toArray(),
                'contacts' => $selectedContractor->contacts->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_primary' => $contact->is_primary,
                    'notes' => $contact->notes,
                ])->values(),
                'interactions' => $selectedContractor->interactions->map(fn ($interaction): array => [
                    'id' => $interaction->id,
                    'contacted_at' => optional($interaction->contacted_at)?->toIso8601String(),
                    'channel' => $interaction->channel,
                    'subject' => $interaction->subject,
                    'summary' => $interaction->summary,
                    'result' => $interaction->result,
                    'author_name' => $interaction->author?->name,
                ])->values(),
                'documents' => $selectedContractor->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'title' => $document->title,
                    'number' => $document->number,
                    'document_date' => optional($document->document_date)?->toDateString(),
                    'status' => $document->status,
                    'notes' => $document->notes,
                ])->values(),
                'orders' => $orders,
            ];
        }

        return Inertia::render('Contractors/Index', [
            'contractors' => $contractors,
            'selectedContractor' => $contractorDetails,
            'legalFormOptions' => [
                ['value' => 'ooo', 'label' => 'ООО'],
                ['value' => 'zao', 'label' => 'ЗАО'],
                ['value' => 'ao', 'label' => 'АО'],
                ['value' => 'ip', 'label' => 'ИП'],
                ['value' => 'samozanyaty', 'label' => 'Самозанятый'],
                ['value' => 'other', 'label' => 'Другое'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractContractorAttributes(array $validated): array
    {
        unset($validated['contacts'], $validated['interactions'], $validated['documents']);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Contractor $contractor, array $validated, ?int $userId): void
    {
        $contractor->contacts()->delete();
        $contractor->interactions()->delete();
        $contractor->documents()->delete();

        foreach ($validated['contacts'] ?? [] as $contact) {
            $contractor->contacts()->create($contact);
        }

        foreach ($validated['interactions'] ?? [] as $interaction) {
            $contractor->interactions()->create([
                ...$interaction,
                'created_by' => $userId,
            ]);
        }

        foreach ($validated['documents'] ?? [] as $document) {
            $contractor->documents()->create([
                ...$document,
                'created_by' => $userId,
            ]);
        }
    }
}
