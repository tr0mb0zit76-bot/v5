<?php

namespace App\Http\Controllers;

use App\Enums\LeadCloseOutcomeFlag;
use App\Http\Requests\AdvanceLeadProcessStageRequest;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreLeadNextStepRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\PrintFormTemplate;
use App\Models\Task;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\LeadCloseOutcomeService;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use App\Services\LeadBusinessProcessService;
use App\Services\LeadConversionService;
use App\Services\LeadPrintFormDraftService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Support\ActivityEventType;
use App\Support\AtiDictionaryOptionCatalog;
use App\Support\ContractorIdentity;
use App\Support\CurrencyDictionary;
use App\Support\LeadCargoItemPayloadNormalizer;
use App\Support\LeadCloseOutcomeFlagCatalog;
use App\Support\LeadRoutePointPayloadNormalizer;
use App\Support\LeadStatus;
use App\Support\LeadTableColumns;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
        private readonly ActivityLedgerService $activityLedger,
        private readonly LeadCloseOutcomeService $leadCloseOutcome,
        private readonly ManagerSalesCoachingInsightsService $salesCoachingInsights,
    ) {}

    public function index(Request $request): Response
    {
        return $this->renderIndexPage($request);
    }

    public function create(Request $request): Response
    {
        return $this->renderIndexPage($request, null, true);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $relations = [
            'counterparty',
            'responsible',
            'cargoItems',
            'routePoints',
            'activities',
            'offers',
            'orders',
            'businessProcess',
            'businessProcessStage',
        ];

        if (Schema::hasTable('tasks')) {
            $relations[] = 'tasks.responsible';
        }

        return $this->renderIndexPage($request, $lead->load($relations));
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $lead = DB::transaction(function () use ($request): Lead {
            $responsibleId = $this->sanitizeResponsibleId($request);

            $lead = Lead::query()->create([
                'number' => $this->nextLeadNumber(),
                'status' => $request->string('status')->toString(),
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $responsibleId,
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

            $this->maybeApplyCloseOutcomeFromRequest($lead, $request);

            if ($this->leadBusinessProcessService->tablesReady() && $request->filled('business_process_id')) {
                $process = BusinessProcess::query()
                    ->where('is_active', true)
                    ->with('stages')
                    ->findOrFail((int) $request->integer('business_process_id'));

                $this->leadBusinessProcessService->startProcess($lead, $process, $request->user());
            }

            return $lead->fresh(['businessProcess', 'businessProcessStage']);
        });

        return to_route('leads.show', $lead);
    }

    /**
     * Быстрое создание контрагента из карточки лида (без отдельного доступа к разделу «Контрагенты»).
     */
    public function storeInlineContractor(StoreInlineOrderContractorRequest $request): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $attributes = [
            'type' => $request->input('type', 'customer'),
            'name' => ContractorIdentity::normalizeName($request->input('name')),
            'inn' => ContractorIdentity::normalizeInn($request->input('inn')),
            'kpp' => $request->string('kpp')->toString() ?: null,
            'legal_address' => $request->string('address')->toString() ?: null,
            'actual_address' => $request->string('address')->toString() ?: null,
            'phone' => $request->string('phone')->toString() ?: null,
            'email' => $request->string('email')->toString() ?: null,
            'contact_person' => $request->string('contact_person')->toString() ?: null,
            'is_active' => true,
            'is_verified' => false,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ];

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $attributes['is_own_company'] = false;
        }

        $contractor = Contractor::query()->create($attributes);

        return response()->json([
            'contractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'type' => $contractor->type,
                'is_own_company' => $contractor->is_own_company ?? false,
            ],
        ], 201);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        DB::transaction(function () use ($request, $lead): void {
            $responsibleId = $this->sanitizeResponsibleId($request, $lead->responsible_id);

            $lead->update([
                'status' => $request->string('status')->toString(),
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $responsibleId,
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

            $this->maybeApplyCloseOutcomeFromRequest($lead, $request);
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

    public function storeNextStep(StoreLeadNextStepRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless(Schema::hasTable('tasks'), 404);

        $responsibleId = $this->sanitizeResponsibleId($request);
        $dueAt = $request->input('due_at');

        Task::query()->create([
            'number' => $this->nextTaskNumber(),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'status' => 'new',
            'priority' => $request->string('priority')->toString() ?: 'high',
            'due_at' => $dueAt,
            'responsible_id' => $responsibleId,
            'created_by' => $request->user()?->id,
            'lead_id' => $lead->id,
        ]);

        if ($dueAt !== null) {
            $lead->forceFill([
                'next_contact_at' => $dueAt,
                'updated_by' => $request->user()?->id,
            ])->save();
        }

        $lead->activities()->create([
            'type' => 'note',
            'subject' => 'Создан следующий шаг',
            'content' => $request->string('title')->toString(),
            'next_action_at' => $dueAt,
            'created_by' => $request->user()?->id,
        ]);

        return to_route('leads.show', $lead);
    }

    public function prepareProposal(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $offer = $this->prepareOrUpdateLeadOffer($lead, $request->user());

        $this->activityLedger->record(
            $lead,
            ActivityEventType::OfferPrepared,
            'КП подготовлено',
            $offer->number,
            ['offer_id' => $offer->id, 'price' => $offer->price],
            null,
            $request->user(),
            $offer,
        );

        $lead->forceFill([
            'status' => 'proposal_ready',
            'updated_by' => $request->user()?->id,
        ])->save();

        return to_route('leads.show', $lead);
    }

    public function storeCommercialFromTemplate(
        Request $request,
        Lead $lead,
        LeadPrintFormDraftService $draftService,
    ): RedirectResponse {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $validated = $request->validate([
            'print_form_template_id' => ['required', 'integer', 'exists:print_form_templates,id'],
        ]);

        $template = PrintFormTemplate::query()->findOrFail($validated['print_form_template_id']);
        abort_if($template->entity_type !== 'lead', 422, 'Черновик можно сформировать только для шаблона лида.');
        abort_if($template->document_type !== 'offer' || $template->document_group !== 'commercial', 422, 'В лидах доступны только коммерческие шаблоны.');
        abort_if(blank($template->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');
        abort_unless($this->isTemplateAvailableForLead($template, $lead), 404);

        $offer = $this->prepareOrUpdateLeadOffer($lead, $request->user(), $template);
        $generatedFile = $draftService->generate($template, $lead);

        $offer->update([
            'generated_file_path' => $generatedFile['path'],
            'payload' => array_merge(is_array($offer->payload) ? $offer->payload : [], [
                'print_form_template_id' => $template->id,
                'print_form_template_name' => $template->name,
                'generated_disk' => $generatedFile['disk'],
            ]),
        ]);

        $this->activityLedger->record(
            $lead,
            ActivityEventType::OfferPrepared,
            'Черновик КП сохранён в карточке',
            $offer->number,
            [
                'offer_id' => $offer->id,
                'print_form_template_id' => $template->id,
                'generated_file_path' => $generatedFile['path'],
            ],
            null,
            $request->user(),
            $offer,
        );

        $lead->forceFill([
            'status' => 'proposal_ready',
            'updated_by' => $request->user()?->id,
        ])->save();

        return to_route('leads.show', $lead)
            ->with('flash', ['type' => 'success', 'message' => 'Черновик КП сохранён в карточке лида.']);
    }

    public function downloadOfferDraft(
        Request $request,
        Lead $lead,
        LeadOffer $offer,
        PrintFormDraftResponseBuilder $draftResponseBuilder,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless($offer->lead_id === $lead->id, 404);
        abort_if(blank($offer->generated_file_path), 404);

        $payload = is_array($offer->payload) ? $offer->payload : [];
        $disk = (string) ($payload['generated_disk'] ?? 'local');
        $downloadName = ($offer->number ?: 'offer-'.$offer->id).'.docx';

        return $draftResponseBuilder->fromStoredDocx(
            $request,
            $disk,
            (string) $offer->generated_file_path,
            $downloadName,
        );
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, LeadConversionService $leadConversionService): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_if($lead->counterparty_id === null, 422, 'Для конверсии лида нужен выбранный контрагент.');

        $order = $leadConversionService->convert($lead, $request->user(), $request->input('own_company_id'));

        return to_route('orders.edit', $order);
    }

    public function generateCommercialDraft(
        Request $request,
        Lead $lead,
        PrintFormTemplate $printFormTemplate,
        LeadPrintFormDraftService $draftService,
        PrintFormDraftResponseBuilder $draftResponseBuilder,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_if($printFormTemplate->entity_type !== 'lead', 422, 'Черновик можно сформировать только для шаблона лида.');
        abort_if($printFormTemplate->document_type !== 'offer' || $printFormTemplate->document_group !== 'commercial', 422, 'В лидах доступны только коммерческие шаблоны.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');
        abort_unless($this->isTemplateAvailableForLead($printFormTemplate, $lead), 404);

        $generatedFile = $draftService->generate($printFormTemplate, $lead);

        return $draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    private function renderWizardPage(Request $request, ?Lead $selectedLead = null, bool $isCreating = false): Response
    {
        return Inertia::render('Leads/Wizard', [
            'selectedLead' => $selectedLead === null ? null : $this->serializeLead($selectedLead),
            'isCreating' => $isCreating,
            ...$this->sharedWizardProps($selectedLead),
        ]);
    }

    private function renderIndexPage(Request $request, ?Lead $selectedLead = null, bool $isCreating = false): Response
    {
        if (! $this->hasLeadsFeatureTables()) {
            return Inertia::render('Leads/Index', [
                'leads' => collect(),
                'leadColumns' => LeadTableColumns::options(),
                'featureUnavailable' => true,
                'selectedLead' => null,
                'isCreating' => false,
                ...$this->sharedWizardProps(),
            ]);
        }

        return Inertia::render('Leads/Index', [
            'leads' => fn () => $this->leadRows($request),
            'leadColumns' => LeadTableColumns::options(),
            'selectedLead' => $selectedLead === null ? null : $this->serializeLead($selectedLead),
            'isCreating' => $isCreating,
            'salesCoachingInsights' => RoleAccess::canViewSalesCoachingInsights($request->user())
                ? $this->salesCoachingInsights->insights(
                    $request->user(),
                    (int) config('outcome_intelligence.coaching_default_days', 90),
                )
                : null,
            ...$this->sharedWizardProps($selectedLead),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function leadRows(Request $request)
    {
        $user = $request->user();
        $leadsScope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        $processReady = $this->leadBusinessProcessService->tablesReady();

        $relations = ['counterparty:id,name', 'responsible:id,name', 'offers:id,lead_id,status,number,offer_date'];
        if ($processReady) {
            $relations[] = 'businessProcess:id,name';
            $relations[] = 'businessProcessStage:id,name,is_terminal';
        }

        $leads = Lead::query()
            ->with($relations)
            ->when(
                $user !== null && ! $user->isAdmin() && $leadsScope !== 'all',
                fn ($query) => $query->where('responsible_id', $user->id)
            )
            ->latest('id')
            ->get()
            ->map(function (Lead $lead) use ($processReady): array {
                $row = [
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
                    'process_name' => null,
                    'current_stage_name' => null,
                    'stage_due_at' => null,
                    'is_stage_overdue' => false,
                ];

                if ($processReady) {
                    $processFields = $this->leadBusinessProcessService->gridProcessFields($lead);
                    if ($processFields !== null) {
                        $row = array_merge($row, $processFields);
                    }
                }

                return $row;
            })
            ->values();

        return $leads;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedWizardProps(?Lead $selectedLead = null): array
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
            'responsibleUsers' => $this->responsibleUsers(request())->values(),
            'statusOptions' => LeadStatus::options(),
            'currentUserId' => request()->user()?->id,
            'canAssignResponsible' => $this->canAssignResponsible(request()),
            'canUseLeadTasks' => $this->canUseLeadTasks(request()),
            'sourceOptions' => [
                ['value' => 'inbound', 'label' => 'Входящий'],
                ['value' => 'outbound', 'label' => 'Исходящий'],
                ['value' => 'referral', 'label' => 'Рекомендация'],
                ['value' => 'website', 'label' => 'Сайт'],
                ['value' => 'existing_customer', 'label' => 'Действующий клиент'],
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
            'currencyOptions' => CurrencyDictionary::options(),
            'printFormTemplateOptions' => $this->availableCommercialTemplates($selectedLead)->values(),
            'businessProcessesEnabled' => $this->leadBusinessProcessService->tablesReady(),
            'businessProcesses' => $this->leadBusinessProcessService->tablesReady()
                ? $this->leadBusinessProcessService->activeProcessesWithStages()
                    ->map(fn (BusinessProcess $process): array => [
                        'id' => $process->id,
                        'name' => $process->name,
                        'description' => $process->description,
                        'stages' => $process->stages->map(fn (BusinessProcessStage $stage): array => [
                            'id' => $stage->id,
                            'name' => $stage->name,
                            'duration_days' => $stage->duration_days,
                            'is_terminal' => $stage->is_terminal,
                            'terminal_outcome' => $stage->terminal_outcome,
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all()
                : [],
            'closeOutcomeOptions' => LeadCloseOutcomeService::optionsForUi(),
            'lostCloseOutcomeOptions' => LeadCloseOutcomeFlagCatalog::lostOptions(),
            'wonCloseOutcomeOptions' => LeadCloseOutcomeFlagCatalog::wonOptions(),
            'cargoTypeOptions' => AtiDictionaryOptionCatalog::options('cargo_type', AtiDictionaryOptionCatalog::fallbackCargoTypeOptions()),
            'packageTypeOptions' => AtiDictionaryOptionCatalog::options('pack_type', AtiDictionaryOptionCatalog::fallbackPackageTypeOptions()),
            'loadingTypeOptions' => AtiDictionaryOptionCatalog::options('loading_type', AtiDictionaryOptionCatalog::fallbackLoadingTypeOptions()),
            'truckBodyTypeOptions' => AtiDictionaryOptionCatalog::options('truck_body_type', AtiDictionaryOptionCatalog::fallbackTruckBodyTypeOptions()),
            'trailerTypeOptions' => AtiDictionaryOptionCatalog::options('trailer_type', AtiDictionaryOptionCatalog::fallbackTrailerTypeOptions()),
            'cargoTitleSuggestions' => Schema::hasTable('cargos')
                ? Cargo::query()
                    ->whereNotNull('title')
                    ->where('title', '!=', '')
                    ->distinct()
                    ->orderBy('title')
                    ->limit(200)
                    ->pluck('title')
                    ->values()
                    ->all()
                : [],
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

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        return $scope === 'all' || $lead->responsible_id === $user->id;
    }

    private function canAssignResponsible(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::resolveVisibilityScopeForUser($user, 'leads') === 'all';
    }

    private function canUseLeadTasks(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && Schema::hasTable('tasks')
            && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks');
    }

    /**
     * @return Collection<int, array{id:int,name:string}>
     */
    private function responsibleUsers(Request $request): Collection
    {
        $user = $request->user();

        if ($user === null) {
            return collect();
        }

        if (! $this->canAssignResponsible($request)) {
            return collect([[
                'id' => $user->id,
                'name' => $user->name,
            ]]);
        }

        $usersQuery = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'manager')
            ->orderBy('users.name');

        if (Schema::hasColumn('users', 'is_active')) {
            $usersQuery->where('users.is_active', true);
        }

        $users = $usersQuery
            ->get(['users.id', 'users.name'])
            ->map(fn ($userRow): array => ['id' => $userRow->id, 'name' => $userRow->name])
            ->values();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return collect([[
            'id' => $user->id,
            'name' => $user->name,
        ]]);
    }

    private function sanitizeResponsibleId(Request $request, ?int $fallbackResponsibleId = null): int
    {
        if (! $this->canAssignResponsible($request)) {
            return $fallbackResponsibleId ?? (int) $request->user()->id;
        }

        $responsibleId = (int) $request->integer('responsible_id');
        if ($responsibleId > 0) {
            return $responsibleId;
        }

        return $fallbackResponsibleId ?? (int) $request->user()->id;
    }

    private function syncNestedData(Lead $lead, Request $request): void
    {
        $lead->routePoints()->delete();
        $lead->cargoItems()->delete();
        $lead->activities()->where('type', '!=', 'status_change')->delete();

        $routeSequence = 0;
        foreach ($request->input('route_points', []) as $routePoint) {
            if (! is_array($routePoint) || ! LeadRoutePointPayloadNormalizer::isMeaningful($routePoint)) {
                continue;
            }

            $routeSequence++;
            $payload = LeadRoutePointPayloadNormalizer::toDatabase($routePoint);
            $payload['sequence'] = $payload['sequence'] ?? $routeSequence;

            $lead->routePoints()->create($payload);
        }

        foreach ($request->input('cargo_items', []) as $cargoItem) {
            if (! is_array($cargoItem) || ! LeadCargoItemPayloadNormalizer::isMeaningful($cargoItem)) {
                continue;
            }

            $lead->cargoItems()->create(LeadCargoItemPayloadNormalizer::toDatabase($cargoItem));
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

    private function nextTaskNumber(): string
    {
        $prefix = 'TSK-'.now()->format('ymd');

        if (! Schema::hasTable('tasks')) {
            return sprintf('%s-%03d', $prefix, 1);
        }

        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    /**
     * @return Collection<int, array{id:int,name:string,code:string,contractor_id:int|null,contractor_name:string|null,is_default:bool}>
     */
    private function availableCommercialTemplates(?Lead $lead = null): Collection
    {
        if (! Schema::hasTable('print_form_templates')) {
            return collect();
        }

        $counterpartyId = $lead?->counterparty_id;

        return PrintFormTemplate::query()
            ->when(
                Schema::hasColumn('print_form_templates', 'contractor_id'),
                fn ($query) => $query->with(['contractor:id,name'])
            )
            ->where('entity_type', 'lead')
            ->where('document_type', 'offer')
            ->where('document_group', 'commercial')
            ->where('is_active', true)
            ->whereNotNull('file_path')
            ->where(function ($query) use ($counterpartyId): void {
                $query->whereNull('contractor_id');

                if ($counterpartyId !== null) {
                    $query->orWhere('contractor_id', $counterpartyId);
                }
            })
            ->orderByRaw('case when contractor_id is null then 1 else 0 end')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (PrintFormTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'contractor_id' => $template->contractor_id,
                'contractor_name' => $template->contractor?->name,
                'is_default' => (bool) $template->is_default,
            ])
            ->values();
    }

    private function isTemplateAvailableForLead(PrintFormTemplate $template, Lead $lead): bool
    {
        if (! $template->is_active || blank($template->file_path) || $template->entity_type !== 'lead') {
            return false;
        }

        if ($template->document_type !== 'offer' || $template->document_group !== 'commercial') {
            return false;
        }

        if ($template->contractor_id === null) {
            return true;
        }

        return (int) $template->contractor_id === (int) $lead->counterparty_id;
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
            'responsible_name' => $lead->responsible?->name,
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
            'close_outcome_primary_flag' => $lead->close_outcome_primary_flag,
            'close_outcome_primary_label' => LeadCloseOutcomeFlagCatalog::label($lead->close_outcome_primary_flag),
            'qualification' => $lead->lead_qualification ?? [],
            'route_points' => $lead->routePoints
                ->map(fn ($point): array => LeadRoutePointPayloadNormalizer::toFrontend($point))
                ->values()
                ->all(),
            'cargo_items' => $lead->cargoItems
                ->map(fn ($cargo): array => LeadCargoItemPayloadNormalizer::toFrontend($cargo))
                ->values()
                ->all(),
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
                'title' => $offer->title,
                'offer_date' => optional($offer->offer_date)->toDateString(),
                'price' => $offer->price,
                'currency' => $offer->currency,
                'generated_file_path' => $offer->generated_file_path,
                'print_template_name' => is_array($offer->payload) ? ($offer->payload['print_form_template_name'] ?? null) : null,
                'sent_at' => optional($offer->sent_at)?->toIso8601String(),
            ])->values()->all(),
            'orders' => $lead->orders->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ])->values()->all(),
            'business_process_id' => $lead->business_process_id,
            'process_progress' => $this->leadBusinessProcessService->progressPayload($lead),
            'tasks' => Schema::hasTable('tasks')
                ? $lead->tasks->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'number' => $task->number,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'status_label' => TaskStatus::label($task->status),
                    'priority' => $task->priority,
                    'due_at' => optional($task->due_at)?->format('Y-m-d\TH:i'),
                    'responsible_id' => $task->responsible_id,
                    'responsible_name' => $task->responsible?->name,
                ])->values()->all()
                : [],
        ];
    }

    public function advanceProcessStage(AdvanceLeadProcessStageRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless($this->leadBusinessProcessService->tablesReady(), 404);

        $stage = BusinessProcessStage::query()->findOrFail((int) $request->integer('stage_id'));

        if ($stage->is_terminal) {
            $this->validateTerminalCloseOutcome($request, $stage);
        }

        $this->leadBusinessProcessService->moveLeadToStage($lead, $stage, $request->user());

        if ($stage->is_terminal && $request->filled('close_outcome_primary_flag')) {
            $flag = LeadCloseOutcomeFlag::from((string) $request->string('close_outcome_primary_flag'));
            $this->leadCloseOutcome->apply(
                $lead->fresh(),
                $flag,
                $request->user(),
                $request->filled('close_outcome_note')
                    ? $request->string('close_outcome_note')->toString()
                    : null,
            );
        }

        return to_route('leads.show', $lead);
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $lead->update([
            'status' => $request->string('status')->toString(),
            'updated_by' => $request->user()?->id,
        ]);

        $lead->activities()->create([
            'type' => 'status_change',
            'subject' => 'Статус лида обновлён',
            'content' => sprintf('Переведён в статус «%s»', LeadStatus::label($lead->status)),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'lead' => [
                'id' => $lead->id,
                'status' => $lead->status,
            ],
        ]);
    }

    private function prepareOrUpdateLeadOffer(Lead $lead, ?Authenticatable $user, ?PrintFormTemplate $template = null): LeadOffer
    {
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

        if ($template !== null) {
            $payload['print_form_template_id'] = $template->id;
            $payload['print_form_template_name'] = $template->name;
        }

        if ($offer === null) {
            return $lead->offers()->create([
                'status' => 'prepared',
                'number' => 'КП-'.$lead->number,
                'title' => $lead->title,
                'offer_date' => now()->toDateString(),
                'price' => $lead->target_price,
                'currency' => $lead->target_currency ?: 'RUB',
                'payload' => $payload,
                'created_by' => $user?->id,
            ]);
        }

        $existingPayload = is_array($offer->payload) ? $offer->payload : [];
        $offer->update([
            'status' => 'prepared',
            'title' => $lead->title,
            'offer_date' => now()->toDateString(),
            'price' => $lead->target_price,
            'currency' => $lead->target_currency ?: 'RUB',
            'payload' => array_merge($existingPayload, $payload),
        ]);

        return $offer->refresh();
    }

    private function maybeApplyCloseOutcomeFromRequest(Lead $lead, StoreLeadRequest $request): void
    {
        if (! $request->filled('close_outcome_primary_flag')) {
            return;
        }

        if (! in_array($lead->status, ['won', 'lost'], true)) {
            return;
        }

        $flag = LeadCloseOutcomeFlag::from((string) $request->string('close_outcome_primary_flag'));

        $this->leadCloseOutcome->apply(
            $lead,
            $flag,
            $request->user(),
            $this->resolveCloseOutcomeNote($request),
        );
    }

    private function resolveCloseOutcomeNote(StoreLeadRequest $request): ?string
    {
        if ($request->filled('close_outcome_note')) {
            return $request->string('close_outcome_note')->toString();
        }

        return $request->string('lost_reason')->toString() ?: null;
    }

    private function validateTerminalCloseOutcome(AdvanceLeadProcessStageRequest $request, BusinessProcessStage $stage): void
    {
        if ($stage->terminal_outcome === 'lost' && ! $request->filled('close_outcome_primary_flag')) {
            throw ValidationException::withMessages([
                'close_outcome_primary_flag' => 'Укажите причину проигрыша перед закрытием лида.',
            ]);
        }

        if (! $request->filled('close_outcome_primary_flag')) {
            return;
        }

        $flag = LeadCloseOutcomeFlag::tryFrom((string) $request->string('close_outcome_primary_flag'));

        if ($flag === null) {
            throw ValidationException::withMessages([
                'close_outcome_primary_flag' => 'Недопустимая причина закрытия.',
            ]);
        }

        if ($stage->terminal_outcome === 'lost' && $flag->terminalOutcome() !== 'lost') {
            throw ValidationException::withMessages([
                'close_outcome_primary_flag' => 'Для этапа отказа выберите причину проигрыша.',
            ]);
        }

        if ($stage->terminal_outcome === 'won' && $flag->terminalOutcome() !== 'won') {
            throw ValidationException::withMessages([
                'close_outcome_primary_flag' => 'Для этапа успеха выберите причину выигрыша.',
            ]);
        }
    }
}
