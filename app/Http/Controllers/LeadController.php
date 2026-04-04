<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Services\LeadConversionService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $this->hasLeadsFeatureTables()) {
            return Inertia::render('Leads/Index', [
                'leads' => collect(),
                'featureUnavailable' => true,
            ]);
        }

        return Inertia::render('Leads/Index', [
            'leads' => $this->leadRows($request),
        ]);
    }

    public function create(Request $request): Response
    {
        if (! $this->hasLeadsFeatureTables()) {
            return Inertia::render('Leads/Wizard', [
                'selectedLead' => null,
                'isCreating' => true,
                'featureUnavailable' => true,
                ...$this->sharedWizardProps(),
            ]);
        }

        return $this->renderWizardPage($request, null, true);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        return $this->renderWizardPage($request, $lead->load([
            'counterparty',
            'responsible',
            'cargoItems',
            'routePoints',
            'activities',
            'offers',
            'orders',
        ]));
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $lead = DB::transaction(function () use ($request): Lead {
            $lead = Lead::query()->create([
                'number' => $this->nextLeadNumber(),
                'status' => $request->string('status')->toString(),
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $request->integer('responsible_id'),
                'title' => $request->string('title')->toString(),
                'description' => $request->string('description')->toString() ?: null,
                'transport_type' => $request->string('transport_type')->toString() ?: null,
                'loading_location' => $request->string('loading_location')->toString() ?: null,
                'unloading_location' => $request->string('unloading_location')->toString() ?: null,
                'planned_shipping_date' => $request->input('planned_shipping_date'),
                'target_price' => $request->input('target_price'),
                'target_currency' => $request->string('target_currency')->toString() ?: 'RUB',
                'calculated_cost' => $request->input('calculated_cost'),
                'expected_margin' => $request->input('expected_margin'),
                'next_contact_at' => $request->input('next_contact_at'),
                'lost_reason' => $request->string('lost_reason')->toString() ?: null,
                'lead_qualification' => $request->input('qualification', []),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($lead, $request);

            return $lead;
        });

        return to_route('leads.show', $lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        DB::transaction(function () use ($request, $lead): void {
            $lead->update([
                'status' => $request->string('status')->toString(),
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $request->integer('responsible_id'),
                'title' => $request->string('title')->toString(),
                'description' => $request->string('description')->toString() ?: null,
                'transport_type' => $request->string('transport_type')->toString() ?: null,
                'loading_location' => $request->string('loading_location')->toString() ?: null,
                'unloading_location' => $request->string('unloading_location')->toString() ?: null,
                'planned_shipping_date' => $request->input('planned_shipping_date'),
                'target_price' => $request->input('target_price'),
                'target_currency' => $request->string('target_currency')->toString() ?: 'RUB',
                'calculated_cost' => $request->input('calculated_cost'),
                'expected_margin' => $request->input('expected_margin'),
                'next_contact_at' => $request->input('next_contact_at'),
                'lost_reason' => $request->string('lost_reason')->toString() ?: null,
                'lead_qualification' => $request->input('qualification', []),
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($lead, $request);
        });

        return to_route('leads.show', $lead);
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $lead->delete();

        return to_route('leads.index');
    }

    public function prepareProposal(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $offer = $lead->offers()->latest('id')->first();

        $payload = [
            'title' => $lead->title,
            'description' => $lead->description,
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency,
            'route' => [
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
            ],
        ];

        if ($offer === null) {
            $lead->offers()->create([
                'status' => 'prepared',
                'number' => 'КП-'.$lead->number,
                'offer_date' => now()->toDateString(),
                'price' => $lead->target_price,
                'currency' => $lead->target_currency ?: 'RUB',
                'payload' => $payload,
                'created_by' => $request->user()?->id,
            ]);
        } else {
            $offer->update([
                'status' => 'prepared',
                'offer_date' => now()->toDateString(),
                'price' => $lead->target_price,
                'currency' => $lead->target_currency ?: 'RUB',
                'payload' => $payload,
            ]);
        }

        $lead->forceFill([
            'status' => 'proposal_ready',
            'updated_by' => $request->user()?->id,
        ])->save();

        return to_route('leads.show', $lead);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, LeadConversionService $leadConversionService): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_if($lead->counterparty_id === null, 422, 'Для конверсии лида нужен выбранный контрагент.');

        $order = $leadConversionService->convert($lead, $request->user(), $request->input('own_company_id'));

        return to_route('orders.edit', $order);
    }

    private function renderWizardPage(Request $request, ?Lead $selectedLead = null, bool $isCreating = false): Response
    {
        return Inertia::render('Leads/Wizard', [
            'selectedLead' => $selectedLead === null ? null : $this->serializeLead($selectedLead),
            'isCreating' => $isCreating,
            ...$this->sharedWizardProps(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function leadRows(Request $request)
    {
        $user = $request->user();
        $roleName = $user?->role?->name;
        $leadsScope = RoleAccess::resolveVisibilityScope($roleName, $user?->role?->visibility_scopes, 'leads');

        $leads = Lead::query()
            ->with(['counterparty:id,name', 'responsible:id,name', 'offers:id,lead_id,status,number,offer_date'])
            ->when(
                $user !== null && ! $user->isAdmin() && $leadsScope !== 'all',
                fn ($query) => $query->where('responsible_id', $user->id)
            )
            ->latest('id')
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'number' => $lead->number,
                'status' => $lead->status,
                'title' => $lead->title,
                'source' => $lead->source,
                'counterparty_name' => $lead->counterparty?->name,
                'responsible_name' => $lead->responsible?->name,
                'planned_shipping_date' => optional($lead->planned_shipping_date)->toDateString(),
                'target_price' => $lead->target_price,
                'target_currency' => $lead->target_currency,
                'has_offer' => $lead->offers->isNotEmpty(),
                'created_at' => optional($lead->created_at)->toIso8601String(),
            ])
            ->values();

        return $leads;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedWizardProps(): array
    {
        $contractorColumns = ['id', 'name', 'inn', 'phone', 'email', 'type'];

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $contractorColumns[] = 'is_own_company';
        }

        $contractors = Contractor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get($contractorColumns);

        return [
            'contractors' => $contractors->values(),
            'responsibleUsers' => DB::table('users')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($userRow): array => ['id' => $userRow->id, 'name' => $userRow->name])
                ->values(),
            'statusOptions' => [
                ['value' => 'new', 'label' => 'Новый'],
                ['value' => 'qualification', 'label' => 'Квалификация'],
                ['value' => 'calculation', 'label' => 'Просчёт'],
                ['value' => 'proposal_ready', 'label' => 'КП подготовлено'],
                ['value' => 'proposal_sent', 'label' => 'КП отправлено'],
                ['value' => 'negotiation', 'label' => 'Переговоры'],
                ['value' => 'won', 'label' => 'Конвертирован'],
                ['value' => 'lost', 'label' => 'Закрыт без сделки'],
                ['value' => 'on_hold', 'label' => 'Отложен'],
            ],
            'sourceOptions' => [
                ['value' => 'inbound', 'label' => 'Входящий'],
                ['value' => 'outbound', 'label' => 'Исходящий'],
                ['value' => 'referral', 'label' => 'Рекомендация'],
                ['value' => 'website', 'label' => 'Сайт'],
                ['value' => 'marketplace', 'label' => 'Маркетплейс'],
                ['value' => 'other', 'label' => 'Другое'],
            ],
            'transportTypeOptions' => [
                ['value' => 'ftl', 'label' => 'FTL'],
                ['value' => 'ltl', 'label' => 'LTL'],
                ['value' => 'container', 'label' => 'Контейнер'],
                ['value' => 'multimodal', 'label' => 'Мультимодальная'],
                ['value' => 'air', 'label' => 'Авиа'],
                ['value' => 'rail', 'label' => 'Ж/д'],
            ],
            'currencyOptions' => [
                ['value' => 'RUB', 'label' => 'RUB'],
                ['value' => 'USD', 'label' => 'USD'],
                ['value' => 'CNY', 'label' => 'CNY'],
                ['value' => 'EUR', 'label' => 'EUR'],
            ],
        ];
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $scope = RoleAccess::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'leads');

        return $scope === 'all' || $lead->responsible_id === $user->id;
    }

    private function syncNestedData(Lead $lead, Request $request): void
    {
        $lead->routePoints()->delete();
        $lead->cargoItems()->delete();
        $lead->activities()->where('type', '!=', 'status_change')->delete();

        foreach ($request->input('route_points', []) as $index => $routePoint) {
            $lead->routePoints()->create([
                'type' => $routePoint['type'],
                'sequence' => $routePoint['sequence'] ?? ($index + 1),
                'address' => $routePoint['address'],
                'normalized_data' => $routePoint['normalized_data'] ?? [],
                'planned_date' => $routePoint['planned_date'] ?? null,
                'contact_person' => $routePoint['contact_person'] ?? null,
                'contact_phone' => $routePoint['contact_phone'] ?? null,
            ]);
        }

        foreach ($request->input('cargo_items', []) as $cargoItem) {
            $lead->cargoItems()->create($cargoItem);
        }

        foreach ($request->input('activities', []) as $activity) {
            $lead->activities()->create([
                'type' => $activity['type'],
                'subject' => $activity['subject'] ?? null,
                'content' => $activity['content'] ?? null,
                'next_action_at' => $activity['next_action_at'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
        }
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    private function hasLeadsFeatureTables(): bool
    {
        return Schema::hasTable('leads')
            && Schema::hasTable('lead_route_points')
            && Schema::hasTable('lead_cargo_items')
            && Schema::hasTable('lead_activities')
            && Schema::hasTable('lead_offers');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLead(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'number' => $lead->number,
            'status' => $lead->status,
            'source' => $lead->source,
            'counterparty_id' => $lead->counterparty_id,
            'responsible_id' => $lead->responsible_id,
            'title' => $lead->title,
            'description' => $lead->description,
            'transport_type' => $lead->transport_type,
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'planned_shipping_date' => optional($lead->planned_shipping_date)->toDateString(),
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency,
            'calculated_cost' => $lead->calculated_cost,
            'expected_margin' => $lead->expected_margin,
            'proposal_sent_at' => optional($lead->proposal_sent_at)?->toIso8601String(),
            'next_contact_at' => optional($lead->next_contact_at)?->format('Y-m-d\TH:i'),
            'lost_reason' => $lead->lost_reason,
            'qualification' => $lead->lead_qualification ?? [],
            'route_points' => $lead->routePoints->map(fn ($point): array => [
                'id' => $point->id,
                'type' => $point->type,
                'sequence' => $point->sequence,
                'address' => $point->address,
                'normalized_data' => $point->normalized_data ?? [],
                'planned_date' => optional($point->planned_date)->toDateString(),
                'contact_person' => $point->contact_person,
                'contact_phone' => $point->contact_phone,
            ])->values()->all(),
            'cargo_items' => $lead->cargoItems->map(fn ($cargo): array => [
                'id' => $cargo->id,
                'name' => $cargo->name,
                'description' => $cargo->description,
                'weight_kg' => $cargo->weight_kg,
                'volume_m3' => $cargo->volume_m3,
                'package_type' => $cargo->package_type,
                'package_count' => $cargo->package_count,
                'dangerous_goods' => $cargo->dangerous_goods,
                'dangerous_class' => $cargo->dangerous_class,
                'hs_code' => $cargo->hs_code,
                'cargo_type' => $cargo->cargo_type,
            ])->values()->all(),
            'activities' => $lead->activities
                ->where('type', '!=', 'status_change')
                ->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'subject' => $activity->subject,
                    'content' => $activity->content,
                    'next_action_at' => optional($activity->next_action_at)?->format('Y-m-d\TH:i'),
                ])
                ->values()
                ->all(),
            'offers' => $lead->offers->map(fn ($offer): array => [
                'id' => $offer->id,
                'status' => $offer->status,
                'number' => $offer->number,
                'offer_date' => optional($offer->offer_date)->toDateString(),
                'price' => $offer->price,
                'currency' => $offer->currency,
                'generated_file_path' => $offer->generated_file_path,
            ])->values()->all(),
            'orders' => $lead->orders->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ])->values()->all(),
        ];
    }
}
