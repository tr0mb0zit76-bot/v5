<?php

namespace App\Http\Controllers;

use App\Enums\LeadCloseOutcomeFlag;
use App\Http\Requests\AdvanceLeadProcessStageRequest;
use App\Http\Requests\CalculateLeadPrecalculationRequest;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\MassUpdateLeadsRequest;
use App\Http\Requests\MergeLeadPortraitRequest;
use App\Http\Requests\SearchImportCostTnVedRequest;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreLeadAttachmentRequest;
use App\Http\Requests\StoreLeadNextStepRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadGridFieldRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\LeadOffer;
use App\Models\PrintFormTemplate;
use App\Models\ProposalHtmlTemplate;
use App\Models\Task;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\LeadAttentionQueueService;
use App\Services\Commercial\LeadCloseOutcomeService;
use App\Services\Commercial\LeadPrecalculationDocumentService;
use App\Services\Commercial\LeadProposalHtmlRenderer;
use App\Services\Commercial\LeadProposalPdfService;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use App\Services\Contractor\ContractorPortraitService;
use App\Services\DaDataService;
use App\Services\ImportCostCalculatorService;
use App\Services\LeadBusinessProcessService;
use App\Services\LeadConversionService;
use App\Services\LeadLinkedTaskService;
use App\Services\LeadPrecalculationService;
use App\Services\LeadPrintFormDraftService;
use App\Services\Leads\LeadBasedOnTemplateBuilder;
use App\Services\Leads\LeadGridMutationService;
use App\Services\Leads\LeadOperationalBriefService;
use App\Services\Leads\LeadRoutePriceBenchmarkService;
use App\Services\Leads\TaskLeadTemplateBuilder;
use App\Services\PrintFormDraftResponseBuilder;
use App\Support\ActivityEventType;
use App\Support\AtiDictionaryOptionCatalog;
use App\Support\CardSmartLinksResolver;
use App\Support\ContractorDecisionMakerLabel;
use App\Support\ContractorIdentity;
use App\Support\CurrencyDictionary;
use App\Support\ImportCostTnVedCatalog;
use App\Support\LeadCargoItemPayloadNormalizer;
use App\Support\LeadCloseOutcomeFlagCatalog;
use App\Support\LeadPerformerPayloadNormalizer;
use App\Support\LeadPrecalculationPayloadNormalizer;
use App\Support\LeadRoutePointPayloadNormalizer;
use App\Support\LeadSource;
use App\Support\LeadStatus;
use App\Support\LeadStatusAutoAdvance;
use App\Support\LeadTableColumns;
use App\Support\LeadViewAuthorization;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use App\Support\TaskViewAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
        private readonly ActivityLedgerService $activityLedger,
        private readonly LeadCloseOutcomeService $leadCloseOutcome,
        private readonly ManagerSalesCoachingInsightsService $salesCoachingInsights,
        private readonly LeadAttentionQueueService $leadAttentionQueue,
        private readonly LeadLinkedTaskService $leadLinkedTaskService,
        private readonly ContractorPortraitService $contractorPortraitService,
        private readonly LeadOperationalBriefService $leadOperationalBriefService,
        private readonly LeadRoutePriceBenchmarkService $leadRoutePriceBenchmark,
        private readonly LeadGridMutationService $leadGridMutationService,
        private readonly LeadPrecalculationService $leadPrecalculationService,
        private readonly ImportCostCalculatorService $importCostCalculatorService,
    ) {}

    public function searchPrecalculationTnVed(SearchImportCostTnVedRequest $request): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $validated = $request->validated();

        return response()->json([
            'items' => ImportCostTnVedCatalog::search(
                (string) $validated['q'],
                (int) ($validated['limit'] ?? 30),
            ),
        ]);
    }

    public function calculatePrecalculation(CalculateLeadPrecalculationRequest $request): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        return response()->json(
            $this->leadPrecalculationService->calculate($request->validated()),
        );
    }

    public function precalculationDocument(
        Request $request,
        Lead $lead,
        LeadPrecalculationDocumentService $documentService,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_if(! is_array($lead->precalculation) || $lead->precalculation === [], 404);

        $format = strtolower((string) $request->query('format', 'html'));
        $document = $documentService->render($lead);

        if ($format === 'pdf') {
            $pdf = $documentService->renderPdf($lead);
            abort_if($pdf === null, 503, 'PDF недоступен: проверьте настройку Gotenberg.');

            $download = $request->boolean('download');
            $fileName = str_replace('.html', '.pdf', $document['file_name']);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$fileName.'"',
            ]);
        }

        return response($document['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$document['file_name'].'"',
        ]);
    }

    public function index(Request $request): Response
    {
        return $this->renderIndexPage($request);
    }

    public function create(
        Request $request,
        LeadBasedOnTemplateBuilder $leadBasedOnTemplateBuilder,
        TaskLeadTemplateBuilder $taskLeadTemplateBuilder,
    ): Response {
        $leadTemplate = null;

        if ($request->filled('from')) {
            $sourceLead = Lead::query()->find((int) $request->query('from'));

            if ($sourceLead instanceof Lead && $this->canAccessLead($request, $sourceLead)) {
                $leadTemplate = $leadBasedOnTemplateBuilder->build($sourceLead);
            }
        } elseif ($request->filled('from_task')) {
            $sourceTask = Task::query()->find((int) $request->query('from_task'));

            if ($sourceTask instanceof Task && $this->canAccessTaskForLeadLinking($request, $sourceTask)) {
                $leadTemplate = $taskLeadTemplateBuilder->build($sourceTask);
            }
        }

        return $this->renderIndexPage($request, null, true, $leadTemplate);
    }

    public function counterpartyAuthorityHint(Request $request): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $validated = $request->validate([
            'contractor_id' => ['required', 'integer', 'min:1'],
        ]);

        $contractor = Contractor::query()
            ->visibleTo($request->user(), 'customer')
            ->with('contacts')
            ->findOrFail((int) $validated['contractor_id']);

        return response()->json([
            'authority' => ContractorDecisionMakerLabel::resolve($contractor),
        ]);
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

        if (Schema::hasTable('lead_attachments')) {
            $relations[] = 'attachments.user:id,name';
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
                'status' => $this->resolveLeadStatusForSave($request),
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $responsibleId,
                'title' => $request->string('title')->toString(),
                'description' => $request->string('description')->toString() ?: null,
                'transport_type' => $request->string('transport_type')->toString() ?: null,
                'loading_location' => $request->string('loading_location')->toString() ?: null,
                'unloading_location' => $request->string('unloading_location')->toString() ?: null,
                'planned_shipping_date' => $request->input('planned_shipping_date'),
                ...$this->leadFinanceAttributes($request),
                ...$this->leadPerformersAttributes($request),
                ...$this->leadPrecalculationAttributes($request),
                'next_contact_at' => $request->input('next_contact_at'),
                'lost_reason' => $request->string('lost_reason')->toString() ?: null,
                'lead_qualification' => $request->input('qualification', []),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($lead, $request);
            $this->syncLeadRouteSummary($lead, $request);

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

        if ($request->filled('link_task_id')) {
            $this->linkCreatedLeadToTask($request, $lead, (int) $request->integer('link_task_id'));
        }

        return to_route('leads.show', $lead);
    }

    /**
     * Быстрое создание контрагента из карточки лида (без отдельного доступа к разделу «Контрагенты»).
     */
    public function storeInlineContractor(StoreInlineOrderContractorRequest $request, DaDataService $daDataService): JsonResponse
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

        $this->mergeInlineContractorPartyFromDaData($attributes, $daDataService);

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $attributes['is_own_company'] = false;
        }

        if (Schema::hasColumn('contractors', 'owner_id')) {
            $attributes['owner_id'] = $request->user()?->id;
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

        $previousStatus = $lead->status;
        $cancelledTasks = 0;
        $resolvedStatus = $lead->status;

        DB::transaction(function () use ($request, $lead, $previousStatus, &$cancelledTasks, &$resolvedStatus): void {
            $responsibleId = $this->sanitizeResponsibleId($request, $lead->responsible_id);
            $newStatus = $this->resolveLeadStatusForSave($request);
            $resolvedStatus = $newStatus;

            $lead->update([
                'status' => $newStatus,
                'source' => $request->string('source')->toString() ?: null,
                'counterparty_id' => $request->input('counterparty_id'),
                'responsible_id' => $responsibleId,
                'title' => $request->string('title')->toString(),
                'description' => $request->string('description')->toString() ?: null,
                'transport_type' => $request->string('transport_type')->toString() ?: null,
                'loading_location' => $request->string('loading_location')->toString() ?: null,
                'unloading_location' => $request->string('unloading_location')->toString() ?: null,
                'planned_shipping_date' => $request->input('planned_shipping_date'),
                ...$this->leadFinanceAttributes($request),
                ...$this->leadPerformersAttributes($request),
                ...$this->leadPrecalculationAttributes($request),
                'next_contact_at' => $request->input('next_contact_at'),
                'lost_reason' => $request->string('lost_reason')->toString() ?: null,
                'lead_qualification' => $request->input('qualification', []),
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($lead, $request);
            $this->syncLeadRouteSummary($lead, $request);

            $this->maybeApplyCloseOutcomeFromRequest($lead, $request);

            if ($previousStatus !== 'lost' && $newStatus === 'lost') {
                $cancelledTasks = $this->leadLinkedTaskService->cancelOpenTasksForLostLead($lead->fresh(), $request->user());
            }
        });

        $followUp = $previousStatus !== 'lost' && $resolvedStatus === 'lost'
            ? $this->leadFollowUpFlash($cancelledTasks)
            : null;

        return $this->redirectToLeadShow($lead, 'Лид сохранён.', $followUp);
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        if (! $lead->trashed()) {
            $lead->delete();
        }

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

    public function storeCommercialFromHtmlTemplate(
        Request $request,
        Lead $lead,
        LeadProposalHtmlRenderer $htmlRenderer,
        LeadProposalPdfService $pdfService,
    ): RedirectResponse {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless(Schema::hasTable('proposal_html_templates'), 404);

        $validated = $request->validate([
            'proposal_html_template_id' => ['required', 'integer', 'exists:proposal_html_templates,id'],
        ]);

        $template = ProposalHtmlTemplate::query()
            ->where('is_active', true)
            ->findOrFail($validated['proposal_html_template_id']);

        $rendered = $htmlRenderer->render($template, $lead);
        $downloadName = Str::slug($template->slug ?: 'proposal').'-lead-'.$lead->id.'.pdf';
        $pdfContents = $pdfService->convertHtmlToPdf($rendered['html'], $downloadName);

        abort_if($pdfContents === null || $pdfContents === '', 503, 'Не удалось сформировать PDF (Gotenberg).');

        $offer = $this->prepareOrUpdateLeadOfferFromHtmlTemplate($lead, $request->user(), $template);
        $storagePath = 'generated-documents/proposals/'.$template->id.'/'.Str::uuid().'-'.$downloadName;
        Storage::disk('local')->put($storagePath, $pdfContents);

        $offer->update([
            'generated_file_path' => $storagePath,
            'payload' => array_merge(is_array($offer->payload) ? $offer->payload : [], [
                'source' => 'html_template',
                'proposal_html_template_id' => $template->id,
                'proposal_html_template_name' => $template->name,
                'rendered_html' => $rendered['html'],
                'generated_disk' => 'local',
                'content_type' => 'application/pdf',
            ]),
        ]);

        $this->activityLedger->record(
            $lead,
            ActivityEventType::OfferPrepared,
            'HTML-КП сохранено в карточке',
            $offer->number,
            [
                'offer_id' => $offer->id,
                'proposal_html_template_id' => $template->id,
                'generated_file_path' => $storagePath,
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
            ->with('flash', ['type' => 'success', 'message' => 'HTML-КП сохранено в карточке лида (PDF).']);
    }

    public function previewHtmlProposal(
        Request $request,
        Lead $lead,
        LeadProposalHtmlRenderer $htmlRenderer,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless(Schema::hasTable('proposal_html_templates'), 404);

        $validated = $request->validate([
            'proposal_html_template_id' => ['required', 'integer', 'exists:proposal_html_templates,id'],
        ]);

        $template = ProposalHtmlTemplate::query()
            ->where('is_active', true)
            ->findOrFail($validated['proposal_html_template_id']);

        $rendered = $htmlRenderer->render($template, $lead);

        return response($rendered['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="proposal-preview.html"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        $contentType = (string) ($payload['content_type'] ?? '');
        $isPdf = $contentType === 'application/pdf'
            || str_ends_with(strtolower((string) $offer->generated_file_path), '.pdf');
        $downloadName = ($offer->number ?: 'offer-'.$offer->id).($isPdf ? '.pdf' : '.docx');

        if ($isPdf) {
            return $draftResponseBuilder->fromStoredPdf(
                $request,
                $disk,
                (string) $offer->generated_file_path,
                $downloadName,
            );
        }

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

        $offer = $this->prepareOrUpdateLeadOffer($lead, $request->user(), $printFormTemplate);
        $generatedFile = $draftService->generate($printFormTemplate, $lead);

        $offer->update([
            'generated_file_path' => $generatedFile['path'],
            'payload' => array_merge(is_array($offer->payload) ? $offer->payload : [], [
                'print_form_template_id' => $printFormTemplate->id,
                'print_form_template_name' => $printFormTemplate->name,
                'generated_disk' => $generatedFile['disk'],
            ]),
        ]);

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

    /**
     * @param  array<string, mixed>|null  $leadTemplate
     */
    private function renderIndexPage(Request $request, ?Lead $selectedLead = null, bool $isCreating = false, ?array $leadTemplate = null): Response
    {
        if (! $this->hasLeadsFeatureTables()) {
            return Inertia::render('Leads/Index', [
                'leads' => collect(),
                'leadColumns' => LeadTableColumns::options(),
                'featureUnavailable' => true,
                'selectedLead' => null,
                'isCreating' => false,
                'leadTemplate' => null,
                ...$this->sharedWizardProps(),
            ]);
        }

        return Inertia::render('Leads/Index', [
            'leads' => fn () => $this->leadRows($request),
            'leadColumns' => LeadTableColumns::options(),
            'selectedLead' => $selectedLead === null ? null : $this->serializeLead($selectedLead),
            'isCreating' => $isCreating,
            'leadTemplate' => $selectedLead === null ? $leadTemplate : null,
            'leadAttentionQueue' => $this->leadAttentionQueue->queueForUser(
                $request->user(),
                (int) config('commercial_nudges.attention_queue_limit', 15),
            ),
            ...$this->sharedWizardProps($selectedLead),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function leadRows(Request $request)
    {
        $user = $request->user();

        $processReady = $this->leadBusinessProcessService->tablesReady();

        $relations = ['counterparty:id,name', 'responsible:id,name', 'offers:id,lead_id,status,number,offer_date'];
        if ($processReady) {
            $relations[] = 'businessProcess:id,name';
            $relations[] = 'businessProcessStage:id,name,is_terminal';
        }

        $leads = Lead::query()
            ->withoutTrashed()
            ->with($relations)
            ->when(
                $user !== null,
                fn ($query) => LeadViewAuthorization::applyLeadsVisibilityScope($query, $user),
            )
            ->latest('id')
            ->get()
            ->map(function (Lead $lead) use ($processReady, $request): array {
                $row = [
                    'id' => $lead->id,
                    'number' => $lead->number,
                    'status' => $lead->status,
                    'title' => $lead->title,
                    'source' => $lead->source,
                    'counterparty_name' => $lead->counterparty?->name,
                    'responsible_id' => $lead->responsible_id,
                    'responsible_name' => $lead->responsible?->name,
                    'business_process_id' => Schema::hasColumn('leads', 'business_process_id')
                        ? $lead->business_process_id
                        : null,
                    'planned_shipping_date' => optional($lead->planned_shipping_date)->toDateString(),
                    'target_price' => $lead->target_price,
                    'target_currency' => $lead->target_currency,
                    'has_offer' => $lead->offers->isNotEmpty(),
                    'created_at' => optional($lead->created_at)->toIso8601String(),
                    'process_name' => null,
                    'current_stage_name' => null,
                    'stage_due_at' => null,
                    'is_stage_overdue' => false,
                    'inline_editable_fields' => $request->user() !== null
                        ? $this->leadGridMutationService->inlineEditableFields($lead, $request->user())
                        : [],
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
            'sourceOptions' => LeadSource::options(),
            'transportTypeOptions' => [
                ['value' => 'ftl', 'label' => 'FTL'],
                ['value' => 'ltl', 'label' => 'LTL'],
                ['value' => 'container', 'label' => 'Контейнер'],
                ['value' => 'multimodal', 'label' => 'Мультимодальная'],
                ['value' => 'air', 'label' => 'Авиа'],
                ['value' => 'rail', 'label' => 'Ж/д'],
            ],
            'currencyOptions' => CurrencyDictionary::options(),
            'paymentFormOptions' => PaymentFormDictionary::options(),
            'printFormTemplateOptions' => $this->availableCommercialTemplates($selectedLead)->values(),
            'proposalHtmlTemplateOptions' => $this->availableProposalHtmlTemplates()->values(),
            'businessProcessesEnabled' => $this->leadBusinessProcessService->tablesReady(),
            'businessProcesses' => $this->leadBusinessProcessService->tablesReady()
                ? $this->leadBusinessProcessService->activeProcessesWithStages()
                    ->map(fn (BusinessProcess $process): array => [
                        'id' => $process->id,
                        'name' => $process->name,
                        'slug' => $process->slug,
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
            'importCostPrecalculationMeta' => $this->importCostCalculatorService->pagePayload(),
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
            'salesCoachingInsights' => RoleAccess::canViewSalesCoachingInsights(request()->user())
                ? $this->salesCoachingInsights->insights(
                    request()->user(),
                    (int) config('outcome_intelligence.coaching_default_days', 90),
                )
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gridFieldPayload(Lead $lead, Request $request): array
    {
        return [
            'id' => $lead->id,
            'source' => $lead->source,
            'status' => $lead->status,
            'responsible_id' => $lead->responsible_id,
            'responsible_name' => $lead->responsible?->name,
            'business_process_id' => Schema::hasColumn('leads', 'business_process_id')
                ? $lead->business_process_id
                : null,
            'inline_editable_fields' => $request->user() !== null
                ? $this->leadGridMutationService->inlineEditableFields($lead, $request->user())
                : [],
        ];
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        return LeadViewAuthorization::userCanViewLead($request->user(), $lead);
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

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');
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

        $currentUserId = (int) $user->id;
        if (! $users->contains(fn (array $row): bool => (int) $row['id'] === $currentUserId)) {
            $users->prepend([
                'id' => $user->id,
                'name' => $user->name,
            ]);
        }

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
        $user = $request->user();

        if ($user === null) {
            return $fallbackResponsibleId ?? 0;
        }

        $allowedIds = $this->responsibleUsers($request)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $responsibleId = (int) $request->integer('responsible_id');
        if ($responsibleId > 0 && in_array($responsibleId, $allowedIds, true)) {
            return $responsibleId;
        }

        if ($fallbackResponsibleId !== null && in_array($fallbackResponsibleId, $allowedIds, true)) {
            return $fallbackResponsibleId;
        }

        return (int) $user->id;
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

            if (! Schema::hasColumn('lead_route_points', 'stage')) {
                unset($payload['stage']);
            }

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

    private function resolveLeadStatusForSave(StoreLeadRequest $request): string
    {
        $requestedStatus = $request->string('status')->toString();

        if ($request->boolean('preserve_status')) {
            return $requestedStatus;
        }

        return LeadStatusAutoAdvance::resolve(
            $requestedStatus,
            $request->input('route_points'),
            $request->input('cargo_items'),
            $request->input('target_price'),
        );
    }

    private function syncLeadRouteSummary(Lead $lead, Request $request): void
    {
        $routePoints = $request->input('route_points', []);
        if (! is_array($routePoints)) {
            return;
        }

        $loadingAddress = null;
        $unloadingAddress = null;
        $plannedDate = null;

        foreach ($routePoints as $routePoint) {
            if (! is_array($routePoint) || ! LeadRoutePointPayloadNormalizer::isMeaningful($routePoint)) {
                continue;
            }

            $type = (string) ($routePoint['type'] ?? '');
            $address = trim((string) ($routePoint['address'] ?? ''));

            if ($type === 'loading' && $loadingAddress === null && $address !== '') {
                $loadingAddress = $address;
            }

            if ($type === 'unloading' && $unloadingAddress === null && $address !== '') {
                $unloadingAddress = $address;
            }

            if ($plannedDate === null && filled($routePoint['planned_date'] ?? null)) {
                $plannedDate = $routePoint['planned_date'];
            }
        }

        $updates = array_filter([
            'loading_location' => $loadingAddress,
            'unloading_location' => $unloadingAddress,
            'planned_shipping_date' => $plannedDate,
        ], fn ($value): bool => $value !== null && $value !== '');

        if ($updates === []) {
            return;
        }

        $lead->forceFill($updates)->save();
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

    private function canAccessTaskForLeadLinking(Request $request, Task $task): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')
            && ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'kanban')) {
            return false;
        }

        return TaskViewAuthorization::userCanViewTask($user, $task);
    }

    private function linkCreatedLeadToTask(Request $request, Lead $lead, int $taskId): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        $task = Task::query()->find($taskId);

        if (! $task instanceof Task) {
            return;
        }

        abort_unless($this->canAccessTaskForLeadLinking($request, $task), 403);

        if ($task->lead_id !== null && (int) $task->lead_id !== (int) $lead->id) {
            throw ValidationException::withMessages([
                'link_task_id' => 'У задачи уже привязан другой лид.',
            ]);
        }

        $task->update(['lead_id' => $lead->id]);
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

    private function availableProposalHtmlTemplates(): Collection
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            return collect();
        }

        return ProposalHtmlTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'version'])
            ->map(fn (ProposalHtmlTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'version' => $template->version,
            ]);
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
    public function previewPortraitMerge(MergeLeadPortraitRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $contractor = $this->resolveLeadCounterpartyForPortrait($lead);

        return response()->json(
            $this->contractorPortraitService->previewMergeFromLead($contractor, $request->qualificationPayload()),
        );
    }

    public function mergePortrait(MergeLeadPortraitRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $contractor = $this->resolveLeadCounterpartyForPortrait($lead);

        $portrait = $this->contractorPortraitService->mergeFromLead(
            $contractor,
            $request->qualificationPayload(),
            $request->user(),
        );

        return response()->json([
            'portrait' => $this->contractorPortraitService->serializePortrait(
                $portrait,
                $contractor->fresh(['portrait', 'contacts', 'interactions']),
            ),
            'counterparty_portrait_coverage_pct' => (int) $portrait->coverage_pct,
            'message' => 'Данные квалификации перенесены в портрет контрагента.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function counterpartyPortraitCoverage(Lead $lead): ?int
    {
        if ($lead->counterparty_id === null || ! Schema::hasTable('contractor_portraits')) {
            return null;
        }

        $contractor = Contractor::query()
            ->with(['portrait', 'contacts', 'interactions'])
            ->find($lead->counterparty_id);

        if ($contractor === null) {
            return null;
        }

        return (int) app(ContractorPortraitService::class)
            ->serializePortrait($contractor->portrait, $contractor)['coverage_pct'];
    }

    private function resolveLeadCounterpartyForPortrait(Lead $lead): Contractor
    {
        if ($lead->counterparty_id === null || ! Schema::hasTable('contractor_portraits')) {
            abort(422, 'У лида не выбран контрагент или модуль портрета недоступен.');
        }

        $contractor = Contractor::query()
            ->with(['portrait', 'contacts', 'interactions'])
            ->find($lead->counterparty_id);

        abort_if($contractor === null, 422, 'Контрагент лида не найден.');

        return $contractor;
    }

    /**
     * @return array{
     *     target_price: mixed,
     *     target_currency: string,
     *     customer_payment_form: string|null,
     *     carrier_payment_form: string|null,
     *     calculated_cost: mixed,
     *     expected_margin: float|null
     * }
     */
    private function leadFinanceAttributes(StoreLeadRequest $request): array
    {
        $targetPrice = $request->input('target_price');
        $calculatedCost = $request->input('calculated_cost');

        return [
            'target_price' => $targetPrice,
            'target_currency' => $request->string('target_currency')->toString() ?: 'RUB',
            'customer_payment_form' => $request->filled('customer_payment_form')
                ? $request->string('customer_payment_form')->toString()
                : null,
            'carrier_payment_form' => $request->filled('carrier_payment_form')
                ? $request->string('carrier_payment_form')->toString()
                : null,
            'calculated_cost' => $calculatedCost,
            'expected_margin' => $this->resolveLeadExpectedMargin($targetPrice, $calculatedCost),
        ];
    }

    private function resolveLeadExpectedMargin(mixed $targetPrice, mixed $calculatedCost): ?float
    {
        if ($targetPrice === null || $targetPrice === '' || $calculatedCost === null || $calculatedCost === '') {
            return null;
        }

        return round((float) $targetPrice - (float) $calculatedCost, 2);
    }

    /**
     * @return array{performers: list<array<string, mixed>>|null}
     */
    private function leadPerformersAttributes(StoreLeadRequest $request): array
    {
        if (! Schema::hasColumn('leads', 'performers')) {
            return [];
        }

        $performers = LeadPerformerPayloadNormalizer::normalizeList(
            $request->input('performers'),
        );

        return [
            'performers' => $performers === [] ? null : $performers,
        ];
    }

    /**
     * @return array{precalculation: array<string, mixed>|null}
     */
    private function leadPrecalculationAttributes(StoreLeadRequest $request): array
    {
        if (! Schema::hasColumn('leads', 'precalculation') || ! $request->has('precalculation')) {
            return [];
        }

        $input = $request->input('precalculation');
        if (! is_array($input)) {
            return ['precalculation' => null];
        }

        $normalized = LeadPrecalculationPayloadNormalizer::normalize($input);

        if ($normalized['goods_lines'] === [] && $normalized['service_lines'] === []) {
            return ['precalculation' => null];
        }

        return [
            'precalculation' => $this->leadPrecalculationService->calculate($normalized),
        ];
    }

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
            'customer_payment_form' => $lead->customer_payment_form,
            'carrier_payment_form' => $lead->carrier_payment_form,
            'calculated_cost' => $lead->calculated_cost,
            'expected_margin' => $lead->expected_margin,
            'proposal_sent_at' => optional($lead->proposal_sent_at)?->toIso8601String(),
            'next_contact_at' => optional($lead->next_contact_at)?->format('Y-m-d\TH:i'),
            'lost_reason' => $lead->lost_reason,
            'close_outcome_primary_flag' => $lead->close_outcome_primary_flag,
            'close_outcome_primary_label' => LeadCloseOutcomeFlagCatalog::label($lead->close_outcome_primary_flag),
            'counterparty_portrait_coverage_pct' => $this->counterpartyPortraitCoverage($lead),
            'qualification' => $lead->lead_qualification ?? [],
            'performers' => LeadPerformerPayloadNormalizer::normalizeList(
                is_array($lead->performers) ? $lead->performers : null,
            ),
            'precalculation' => $this->leadPrecalculationService->normalize(
                is_array($lead->precalculation) ? $lead->precalculation : null,
            ),
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
                'print_template_name' => is_array($offer->payload)
                    ? ($offer->payload['print_form_template_name'] ?? $offer->payload['proposal_html_template_name'] ?? null)
                    : null,
                'proposal_source' => is_array($offer->payload) ? ($offer->payload['source'] ?? null) : null,
                'sent_at' => optional($offer->sent_at)?->toIso8601String(),
            ])->values()->all(),
            'orders' => $lead->orders->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ])->values()->all(),
            'business_process_id' => $lead->business_process_id,
            'process_progress' => $this->leadBusinessProcessService->progressPayload($lead),
            'operational_brief' => $this->leadOperationalBriefService->build($lead),
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
            'attachments' => Schema::hasTable('lead_attachments') && $lead->relationLoaded('attachments')
                ? $lead->attachments->map(fn (LeadAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'created_at' => optional($attachment->created_at)?->toIso8601String(),
                    'uploaded_by' => $attachment->user?->name,
                    'download_url' => route('leads.attachments.download', [$lead, $attachment]),
                ])->values()->all()
                : [],
            'route_price_benchmark' => $this->leadRoutePriceBenchmark->benchmarkForLead($lead),
            'smart_links' => app(CardSmartLinksResolver::class)->forLead($lead, request()->user()),
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

        $cancelledTasks = $this->leadBusinessProcessService->moveLeadToStage($lead, $stage, $request->user());

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

        $followUp = $stage->terminal_outcome === 'lost'
            ? $this->leadFollowUpFlash($cancelledTasks)
            : null;

        return $this->redirectToLeadShow($lead, 'Этап бизнес-процесса обновлён.', $followUp);
    }

    public function updateGridField(UpdateLeadGridFieldRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $result = $this->leadGridMutationService->applyField(
            $lead,
            $request->user(),
            $request->string('field')->toString(),
            $request->input('value'),
        );

        if (! $result['updated']) {
            return response()->json([
                'message' => $result['message'] ?? 'Не удалось сохранить изменение.',
            ], 422);
        }

        $lead->refresh()->load(['responsible:id,name']);

        return response()->json([
            'lead' => $this->gridFieldPayload($lead, $request),
        ]);
    }

    public function massUpdate(MassUpdateLeadsRequest $request): JsonResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);

        $leadIds = collect($request->input('lead_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $result = $this->leadGridMutationService->massApply(
            $request->user(),
            $leadIds,
            $request->string('action')->toString(),
            $request->input('value'),
        );

        $message = match ($request->string('action')->toString()) {
            'delete' => sprintf(
                'Удалено лидов: %d. Пропущено: %d.',
                $result['updated_count'],
                $result['skipped_count'],
            ),
            default => sprintf(
                'Обновлено лидов: %d. Пропущено: %d.',
                $result['updated_count'],
                $result['skipped_count'],
            ),
        };

        return response()->json([
            'message' => $message,
            ...$result,
        ]);
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

    private function prepareOrUpdateLeadOfferFromHtmlTemplate(Lead $lead, ?Authenticatable $user, ProposalHtmlTemplate $template): LeadOffer
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
            'proposal_html_template_id' => $template->id,
            'proposal_html_template_name' => $template->name,
            'source' => 'html_template',
        ];

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

    /**
     * @return array{cancelled_tasks: int, suggested_title: string}
     */
    private function leadFollowUpFlash(int $cancelledTasks): array
    {
        return [
            'cancelled_tasks' => $cancelledTasks,
            'suggested_title' => 'Узнать новости у клиента',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mergeInlineContractorPartyFromDaData(array &$attributes, DaDataService $daDataService): void
    {
        $inn = is_string($attributes['inn'] ?? null) ? preg_replace('/\D+/', '', $attributes['inn']) : '';
        if ($inn === null || ! in_array(strlen($inn), [10, 12], true)) {
            return;
        }

        $suggestions = $daDataService->suggestParty($inn, 1);
        $suggestion = $suggestions[0] ?? null;
        if (! is_array($suggestion)) {
            return;
        }

        $party = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];
        $partyName = is_array($party['name'] ?? null) ? $party['name'] : [];

        if (Schema::hasColumn('contractors', 'full_name')) {
            $fullName = trim((string) ($partyName['full_with_opf'] ?? $partyName['full'] ?? ''));
            if ($fullName !== '') {
                $attributes['full_name'] = $fullName;
            }
        }

        if (($attributes['kpp'] ?? null) === null || $attributes['kpp'] === '') {
            $kpp = trim((string) ($party['kpp'] ?? ''));
            if ($kpp !== '') {
                $attributes['kpp'] = $kpp;
            }
        }

        $shortName = trim((string) ($partyName['short_with_opf'] ?? $partyName['short'] ?? $suggestion['value'] ?? ''));
        if ($shortName !== '' && trim((string) ($attributes['name'] ?? '')) === '') {
            $attributes['name'] = ContractorIdentity::normalizeName($shortName);
        }
    }

    /**
     * @param  array{cancelled_tasks: int, suggested_title: string}|null  $followUp
     */
    private function redirectToLeadShow(Lead $lead, ?string $message = null, ?array $followUp = null): RedirectResponse
    {
        $flash = [
            'type' => 'success',
            'message' => $message,
        ];

        if ($followUp !== null) {
            $flash['lead_follow_up'] = $followUp;
        }

        return to_route('leads.show', $lead)->with('flash', $flash);
    }

    public function storeAttachment(StoreLeadAttachmentRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless(Schema::hasTable('lead_attachments'), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $file = $request->file('file');
        $path = $file->store('leads/attachments', 'public');

        $lead->attachments()->create([
            'user_id' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return $this->redirectToLeadShow($lead, 'Файл добавлен к лиду.');
    }

    public function destroyAttachment(Request $request, Lead $lead, LeadAttachment $leadAttachment): RedirectResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless(Schema::hasTable('lead_attachments'), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless($leadAttachment->lead_id === $lead->id, 404);

        Storage::disk($leadAttachment->disk)->delete($leadAttachment->path);
        $leadAttachment->delete();

        return $this->redirectToLeadShow($lead, 'Вложение удалено.');
    }

    public function downloadAttachment(Request $request, Lead $lead, LeadAttachment $leadAttachment): BinaryFileResponse|StreamedResponse
    {
        abort_unless($this->hasLeadsFeatureTables(), 404);
        abort_unless(Schema::hasTable('lead_attachments'), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);
        abort_unless($leadAttachment->lead_id === $lead->id, 404);

        return Storage::disk($leadAttachment->disk)->download($leadAttachment->path, $leadAttachment->original_name);
    }
}
