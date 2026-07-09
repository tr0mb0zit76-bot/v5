<?php

use App\Http\Controllers\ActivityTimelineController;
use App\Http\Controllers\BudgetingController;
use App\Http\Controllers\CabinetNotificationController;
use App\Http\Controllers\CommandBarAgentController;
use App\Http\Controllers\CompanyPlanningController;
use App\Http\Controllers\ContractorContactTrakloController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ContractorInsightDraftController;
use App\Http\Controllers\ContractorPortraitController;
use App\Http\Controllers\ContractorPrintFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispositionController;
use App\Http\Controllers\DocumentOptimizeController;
use App\Http\Controllers\DocumentRegistryController;
use App\Http\Controllers\DocumentUploadBudgetEstimateController;
use App\Http\Controllers\External\ExternalInviteController;
use App\Http\Controllers\FinanceDocumentController;
use App\Http\Controllers\FinanceIndexController;
use App\Http\Controllers\FinanceReconciliationController;
use App\Http\Controllers\FleetDriverController;
use App\Http\Controllers\FleetEfficiencyController;
use App\Http\Controllers\FleetTripController;
use App\Http\Controllers\FleetVehicleController;
use App\Http\Controllers\GridViewController;
use App\Http\Controllers\ImportCostCalculatorController;
use App\Http\Controllers\Integrations\AstralEpdWebhookController;
use App\Http\Controllers\Integrations\OneCFreshEtrnController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadOfferMailController;
use App\Http\Controllers\LoadBoardController;
use App\Http\Controllers\LoadingPlannerController;
use App\Http\Controllers\MailMailboxController;
use App\Http\Controllers\MailThreadAnalysisController;
use App\Http\Controllers\ManagementAccountingController;
use App\Http\Controllers\ManagementAccountingImportController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\Mobile\MobileAppUpdateController;
use App\Http\Controllers\Mobile\MobileCounterpartyShellController;
use App\Http\Controllers\Mobile\MobileShellController;
use App\Http\Controllers\Orders\OrderBasicTermsController;
use App\Http\Controllers\Orders\OrderDocumentsModalController;
use App\Http\Controllers\Orders\OrderDocumentWorkflowController;
use App\Http\Controllers\Orders\OrderIndexController;
use App\Http\Controllers\Orders\OrderIntakeController;
use App\Http\Controllers\Orders\OrderPortalInviteController;
use App\Http\Controllers\Orders\OrderTransportSummaryController;
use App\Http\Controllers\Orders\OrderWizardController;
use App\Http\Controllers\PaymentScheduleController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\Portal\OrderCarrierPortalController;
use App\Http\Controllers\Portal\OrderCustomerPortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProposalHtmlTemplateController;
use App\Http\Controllers\PublicOrderDocumentVerificationController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PublicSlaDocumentController;
use App\Http\Controllers\PublicTransportRequestController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SalesAssistantController;
use App\Http\Controllers\SalesScriptController;
use App\Http\Controllers\SalesScriptEditorController;
use App\Http\Controllers\SettingsAiAnalyticsController;
use App\Http\Controllers\SettingsBusinessProcessController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsDictionariesController;
use App\Http\Controllers\SettingsKpiController;
use App\Http\Controllers\SettingsMcpIntegrationController;
use App\Http\Controllers\SettingsSystemController;
use App\Http\Controllers\SettingsTableManagementController;
use App\Http\Controllers\SettingsTemplateController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Витрина и CRM на разных хостах: SHOWCASE_DOMAIN (можно несколько через запятую) и CRM_DOMAIN.
// Nextcloud (nc.*) — отдельный vhost, не Laravel.
$crmDomain = (string) config('app.crm_domain');
/** @var list<string> $showcaseHosts */
$showcaseHosts = config('app.showcase_hosts', []);
$sameShowcaseAndCrmHost = $crmDomain !== ''
    && count($showcaseHosts) === 1
    && strcasecmp($crmDomain, $showcaseHosts[0]) === 0;

Route::get('/transport-request', [PublicTransportRequestController::class, 'create'])
    ->name('public.transport-request.create');
Route::post('/transport-request', [PublicTransportRequestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('public.transport-request.store');

if ($sameShowcaseAndCrmHost) {
    // Один хост: лендинг и кабинет на одном origin (без редиректа витрина→CRM).
    foreach ($showcaseHosts as $index => $showcaseDomain) {
        $namePublicRoutes = $index === 0;

        Route::domain($showcaseDomain)->group(function () use ($namePublicRoutes) {
            $home = Route::get('/', function () {
                if (auth()->check()) {
                    return redirect('/dashboard');
                }

                return app(PublicSiteController::class)->home();
            });
            if ($namePublicRoutes) {
                $home->name('public.home');
            }

            Route::controller(PublicSiteController::class)->group(function () use ($namePublicRoutes) {
                $about = Route::get('/about', 'about');
                $services = Route::get('/services', 'services');
                $cases = Route::get('/cases', 'cases');
                $sla = Route::get('/sla', 'sla');
                $contacts = Route::get('/contacts', 'contacts');
                if ($namePublicRoutes) {
                    $about->name('public.about');
                    $services->name('public.services');
                    $cases->name('public.cases');
                    $sla->name('public.sla');
                    $contacts->name('public.contacts');
                }
            });

            $slaDocument = Route::get('/sla/documents/{document}', [PublicSlaDocumentController::class, 'show']);
            if ($namePublicRoutes) {
                $slaDocument->name('public.sla.document');
            }

            $localeSwitch = Route::get('/locale/{locale}', [PublicSiteController::class, 'switchLocale']);
            if ($namePublicRoutes) {
                $localeSwitch->name('public.locale.switch');
            }

            $boost = Route::any('/_boost/browser-logs', fn () => response()->noContent());
            if ($namePublicRoutes) {
                $boost->name('public.boost.browser-logs');
            }
        });
    }
} else {
    foreach ($showcaseHosts as $index => $showcaseDomain) {
        $namePublicRoutes = $index === 0;

        Route::domain($showcaseDomain)->group(function () use ($crmDomain, $namePublicRoutes) {
            Route::controller(PublicSiteController::class)->group(function () use ($namePublicRoutes) {
                $home = Route::get('/', 'home');
                $about = Route::get('/about', 'about');
                $services = Route::get('/services', 'services');
                $cases = Route::get('/cases', 'cases');
                $sla = Route::get('/sla', 'sla');
                $contacts = Route::get('/contacts', 'contacts');
                if ($namePublicRoutes) {
                    $home->name('public.home');
                    $about->name('public.about');
                    $services->name('public.services');
                    $cases->name('public.cases');
                    $sla->name('public.sla');
                    $contacts->name('public.contacts');
                }
            });

            $slaDocument = Route::get('/sla/documents/{document}', [PublicSlaDocumentController::class, 'show']);
            if ($namePublicRoutes) {
                $slaDocument->name('public.sla.document');
            }

            $localeSwitch = Route::get('/locale/{locale}', [PublicSiteController::class, 'switchLocale']);
            if ($namePublicRoutes) {
                $localeSwitch->name('public.locale.switch');
            }

            $boost = Route::any('/_boost/browser-logs', fn () => response()->noContent());
            if ($namePublicRoutes) {
                $boost->name('public.boost.browser-logs');
            }

            Route::any('/{any}', function () use ($crmDomain) {
                $scheme = request()->isSecure() ? 'https' : 'http';
                $path = ltrim((string) request()->path(), '/');
                $queryString = request()->getQueryString();
                $target = sprintf('%s://%s/%s', $scheme, $crmDomain, $path);

                if (is_string($queryString) && $queryString !== '') {
                    $target .= '?'.$queryString;
                }

                return redirect()->to($target);
            })->where('any', '.*');
        });
    }

    Route::domain($crmDomain)->get('/', function () {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return redirect()->route('login');
    });
}

Route::middleware('throttle:60,1')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/carrier/{token}', [OrderCarrierPortalController::class, 'show'])->name('carrier.show');
    Route::post('/carrier/{token}', [OrderCarrierPortalController::class, 'store'])->name('carrier.store');
    Route::post('/carrier/{token}/documents', [OrderCarrierPortalController::class, 'storeDocument'])->name('carrier.documents.store');
    Route::post('/carrier/{token}/fleet-documents', [OrderCarrierPortalController::class, 'storeFleetDocument'])->name('carrier.fleet-documents.store');
    Route::get('/customer/{token}', [OrderCustomerPortalController::class, 'show'])->name('customer.show');
    Route::post('/customer/{token}/documents', [OrderCustomerPortalController::class, 'storeDocument'])->name('customer.documents.store');
});

Route::middleware('throttle:60,1')
    ->get('/verify/order-documents/{orderDocument}', [PublicOrderDocumentVerificationController::class, 'show'])
    ->name('print-verification.order-documents.show');

Route::get('/mobile/app-update', MobileAppUpdateController::class)
    ->name('mobile.app-update');

Route::middleware('throttle:30,1')->prefix('external/invite')->name('external.invite.')->group(function (): void {
    Route::get('/{token}', [ExternalInviteController::class, 'show'])->name('show');
    Route::post('/{token}', [ExternalInviteController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/agent/command-bar/chat', [CommandBarAgentController::class, 'chat'])
        ->middleware('throttle:agent-command-bar')
        ->name('agent.command-bar.chat');
    Route::post('/agent/command-bar/feedback', [CommandBarAgentController::class, 'feedback'])
        ->middleware('throttle:agent-command-bar')
        ->name('agent.command-bar.feedback');

    Route::get('/dashboard', DashboardController::class)->middleware('visibility.area:dashboard')->name('dashboard');

    Route::get('/reports', [ReportsController::class, 'index'])->middleware('visibility.area:reports')->name('reports.index');

    Route::controller(LeadController::class)->middleware('visibility.area:leads')->group(function () {
        Route::get('/leads', 'index')->name('leads.index');
        Route::get('/leads/create', 'create')->name('leads.create');
        Route::post('/leads', 'store')->name('leads.store');
        Route::post('/leads/contractors', 'storeInlineContractor')->name('leads.contractors.store');
        Route::get('/leads/counterparty-authority-hint', 'counterpartyAuthorityHint')->name('leads.counterparty-authority-hint');
        Route::post('/leads/mass-update', 'massUpdate')->name('leads.mass-update');
        Route::get('/leads/precalculation/tn-ved/search', 'searchPrecalculationTnVed')->name('leads.precalculation.tn-ved.search');
        Route::post('/leads/precalculation/calculate', 'calculatePrecalculation')->name('leads.precalculation.calculate');
        Route::get('/leads/{lead}/precalculation/document', 'precalculationDocument')->name('leads.precalculation.document');
        Route::get('/leads/{lead}', 'show')->name('leads.show');
        Route::patch('/leads/{lead}', 'update')->name('leads.update');
        Route::delete('/leads/{lead}', 'destroy')->name('leads.destroy');
        Route::post('/leads/{lead}/portrait-merge', 'mergePortrait')->name('leads.portrait-merge');
        Route::get('/leads/{lead}/portrait-merge/preview', 'previewPortraitMerge')->name('leads.portrait-merge.preview');
        Route::post('/leads/{lead}/proposal', 'prepareProposal')->name('leads.proposal');
        Route::post('/leads/{lead}/commercial/from-template', 'storeCommercialFromTemplate')->name('leads.commercial.from-template');
        Route::post('/leads/{lead}/proposal/from-html-template', 'storeCommercialFromHtmlTemplate')->name('leads.proposal.from-html-template');
        Route::get('/leads/{lead}/proposal/html-preview', 'previewHtmlProposal')->name('leads.proposal.html-preview');
        Route::get('/leads/{lead}/offers/{offer}/draft', 'downloadOfferDraft')->name('leads.offers.draft');
        Route::post('/leads/{lead}/next-step', 'storeNextStep')->name('leads.next-step.store');
        Route::patch('/leads/{lead}/process-stage', 'advanceProcessStage')->name('leads.process-stage');
        Route::post('/leads/{lead}/attachments', 'storeAttachment')->name('leads.attachments.store');
        Route::delete('/leads/{lead}/attachments/{leadAttachment}', 'destroyAttachment')->name('leads.attachments.destroy');
        Route::get('/leads/{lead}/attachments/{leadAttachment}/download', 'downloadAttachment')->name('leads.attachments.download');
        Route::get('/leads/{lead}/templates/{printFormTemplate}/draft', 'generateCommercialDraft')->name('leads.templates.generate-draft');
        Route::post('/leads/{lead}/convert', 'convert')->name('leads.convert');
        Route::get('/leads/{lead}/activity-timeline', [ActivityTimelineController::class, 'showForLead'])->name('leads.activity-timeline');
        Route::post('/leads/{lead}/offers/{offer}/send-email', [LeadOfferMailController::class, 'send'])->name('leads.offers.send-email');
    });

    Route::middleware('visibility.area:mail')->prefix('mail')->name('mail.')->group(function () {
        Route::get('/', [MailMailboxController::class, 'index'])->name('index');
        Route::get('/link-options', [MailMailboxController::class, 'linkOptions'])->name('link-options');
        Route::get('/threads/{mailThread}', [MailMailboxController::class, 'show'])->name('threads.show');
        Route::post('/send', [MailMailboxController::class, 'send'])->name('send');
        Route::post('/threads/{mailThread}/reply', [MailMailboxController::class, 'reply'])->name('threads.reply');
        Route::patch('/threads/{mailThread}/links', [MailMailboxController::class, 'updateLinks'])->name('threads.links');
        Route::delete('/threads/{mailThread}', [MailMailboxController::class, 'destroy'])->name('threads.destroy');
        Route::patch('/messages/{mailMessage}/importance', [MailMailboxController::class, 'updateImportance'])->name('messages.importance');
        Route::get('/messages/{mailMessage}/attachments/{attachmentIndex}', [MailMailboxController::class, 'downloadAttachment'])
            ->whereNumber('attachmentIndex')
            ->name('messages.attachments.download');
        Route::get('/messages/{mailMessage}/attachments/{attachmentIndex}/preview', [MailMailboxController::class, 'previewAttachment'])
            ->whereNumber('attachmentIndex')
            ->name('messages.attachments.preview');
        Route::post('/threads/{mailThread}/ai/summarize', [MailThreadAnalysisController::class, 'summarize'])->name('threads.ai.summarize');
        Route::post('/threads/{mailThread}/ai/draft-reply', [MailThreadAnalysisController::class, 'draftReply'])->name('threads.ai.draft-reply');
        Route::post('/threads/{mailThread}/ai/next-step', [MailThreadAnalysisController::class, 'suggestNextStep'])->name('threads.ai.next-step');
        Route::post('/ai/feedback', [MailThreadAnalysisController::class, 'feedback'])->name('ai.feedback');
    });

    Route::middleware('visibility.area:sales_assistant_scripts')->group(function () {
        Route::controller(SalesScriptController::class)->group(function () {
            Route::get('/scripts', 'index')->name('scripts.index');
            Route::post('/scripts/sessions', 'storeSession')->name('scripts.sessions.store');
            Route::get('/scripts/sessions/{sales_script_play_session}', 'showSession')->name('scripts.sessions.show');
            Route::post('/scripts/sessions/{sales_script_play_session}/advance', 'advance')->name('scripts.sessions.advance');
            Route::post('/scripts/sessions/{sales_script_play_session}/trainer-message', 'trainerMessage')->name('scripts.sessions.trainer-message');
            Route::patch('/scripts/sessions/{sales_script_play_session}/trainer-messages/{trainer_message}/peer-reaction', 'updateTrainerMessagePeerReaction')->name('scripts.sessions.trainer-message.peer-reaction');
            Route::patch('/scripts/sessions/{sales_script_play_session}/trainer-meta', 'updateTrainerMeta')->name('scripts.sessions.trainer-meta');
            Route::post('/scripts/sessions/{sales_script_play_session}/complete', 'complete')->name('scripts.sessions.complete');
            Route::get('/scripts/sessions/{sales_script_play_session}/leads', 'searchLeads')->name('scripts.sessions.leads.search');
            Route::post('/scripts/sessions/{sales_script_play_session}/lead', 'linkLead')->name('scripts.sessions.lead.link');
            Route::post('/scripts/sessions/{sales_script_play_session}/lead/create', 'createLead')->name('scripts.sessions.lead.create');
        });
    });

    Route::prefix('sales-assistant')->name('sales-assistant.')->group(function () {
        Route::middleware('visibility.area:sales_assistant_book')->group(function () {
            Route::controller(SalesAssistantController::class)->group(function () {
                Route::get('/book', 'book')->name('book');
                Route::post('/book/articles', 'storeBookArticle')->name('book.articles.store');
                Route::post('/book/articles/{salesBookArticle}/feedback', 'storeBookArticleFeedback')->name('book.articles.feedback');
                Route::post('/book/articles/{salesBookArticle}/quiz-attempt', 'storeBookQuizAttempt')->name('book.articles.quiz-attempt');
                Route::post('/book/articles/{salesBookArticle}/cover', 'uploadBookCover')->name('book.articles.cover.upload');
                Route::delete('/book/articles/{salesBookArticle}/cover', 'destroyBookCover')->name('book.articles.cover.destroy');
                Route::patch('/book/articles/{salesBookArticle}', 'updateBookArticle')->name('book.articles.update');
                Route::patch('/book/articles/{salesBookArticle}/move', 'moveBookArticle')->name('book.articles.move');
                Route::delete('/book/articles/{salesBookArticle}', 'destroyBookArticle')->name('book.articles.destroy');
                Route::post('/book/import', 'importBookArticle')->name('book.import');
                Route::post('/book/assets', 'uploadBookAsset')->name('book.assets.upload');
                Route::get('/book/assets', 'showBookAsset')->name('book.assets.show');
            });
        });

        Route::middleware('visibility.area:sales_assistant_counter')->group(function () {
            Route::controller(SalesAssistantController::class)->prefix('counter')->name('counter.')->group(function () {
                Route::get('/', 'counter')->name('index');
                Route::post('/calculate', 'calculateCounter')->name('calculate');
            });
        });

        Route::middleware('visibility.area:sales_assistant_trainer')->group(function () {
            Route::controller(SalesAssistantController::class)->group(function () {
                Route::get('/trainer', 'trainer')->name('trainer');
            });
        });

        Route::middleware('visibility.area:sales_assistant_trainer_analytics')->group(function () {
            Route::controller(SalesAssistantController::class)->group(function () {
                Route::get('/trainer/analytics', 'trainerAnalytics')->name('trainer.analytics');
            });
        });

        Route::middleware('visibility.area:sales_assistant_book')->group(function () {
            Route::controller(SalesAssistantController::class)->group(function () {
                Route::get('/book/quiz-analytics', 'bookQuizAnalytics')->name('book.quiz-analytics');
            });
        });
    });

    Route::prefix('scripts/editor')
        ->name('scripts.editor.')
        ->middleware(['visibility.area:sales_assistant_scripts', 'can.manage.sales.scripts'])
        ->group(function () {
            Route::get('/', [SalesScriptEditorController::class, 'index'])->name('index');
            Route::post('/scripts', [SalesScriptEditorController::class, 'storeScript'])->name('scripts.store');
            Route::patch('/scripts/{sales_script}', [SalesScriptEditorController::class, 'updateScript'])->name('scripts.update');
            Route::delete('/scripts/{sales_script}', [SalesScriptEditorController::class, 'destroyScript'])->name('scripts.destroy');
            Route::post('/scripts/{sales_script}/versions', [SalesScriptEditorController::class, 'storeVersion'])->name('scripts.versions.store');
            Route::get('/versions/{sales_script_version}', [SalesScriptEditorController::class, 'showVersion'])->name('versions.show');
            Route::get('/versions/{sales_script_version}/graph', [SalesScriptEditorController::class, 'showGraph'])->name('versions.graph');
            Route::get('/versions/{sales_script_version}/analytics', [SalesScriptEditorController::class, 'analytics'])->name('versions.analytics');
            Route::get('/versions/{sales_script_version}/analytics/export', [SalesScriptEditorController::class, 'exportAnalytics'])->name('versions.analytics.export');
            Route::patch('/versions/{sales_script_version}', [SalesScriptEditorController::class, 'updateVersion'])->name('versions.update');
            Route::put('/versions/{sales_script_version}/graph', [SalesScriptEditorController::class, 'updateGraph'])->name('versions.graph.update');
            Route::post('/versions/{sales_script_version}/publish', [SalesScriptEditorController::class, 'publishVersion'])->name('versions.publish');
            Route::post('/versions/{sales_script_version}/unpublish', [SalesScriptEditorController::class, 'unpublishVersion'])->name('versions.unpublish');
            Route::post('/versions/{sales_script_version}/nodes', [SalesScriptEditorController::class, 'storeNode'])->name('versions.nodes.store');
            Route::post('/versions/{sales_script_version}/transitions', [SalesScriptEditorController::class, 'storeTransition'])->name('versions.transitions.store');
            Route::patch('/nodes/{sales_script_node}', [SalesScriptEditorController::class, 'updateNode'])->name('nodes.update');
            Route::delete('/nodes/{sales_script_node}', [SalesScriptEditorController::class, 'destroyNode'])->name('nodes.destroy');
            Route::patch('/transitions/{sales_script_transition}', [SalesScriptEditorController::class, 'updateTransition'])->name('transitions.update');
            Route::delete('/transitions/{sales_script_transition}', [SalesScriptEditorController::class, 'destroyTransition'])->name('transitions.destroy');
            Route::post('/capture-fields', [SalesScriptEditorController::class, 'storeCaptureField'])->name('capture-fields.store');
            Route::patch('/capture-fields/{sales_script_capture_field}', [SalesScriptEditorController::class, 'updateCaptureField'])->name('capture-fields.update');
            Route::delete('/capture-fields/{sales_script_capture_field}', [SalesScriptEditorController::class, 'destroyCaptureField'])->name('capture-fields.destroy');
            Route::post('/node-templates', [SalesScriptEditorController::class, 'storeNodeTemplate'])->name('node-templates.store');
            Route::patch('/node-templates/{sales_script_node_template}', [SalesScriptEditorController::class, 'updateNodeTemplate'])->name('node-templates.update');
            Route::delete('/node-templates/{sales_script_node_template}', [SalesScriptEditorController::class, 'destroyNodeTemplate'])->name('node-templates.destroy');
        });

    Route::get('/orders', OrderIndexController::class)->middleware('visibility.area:orders')->name('orders.index');
    Route::get('/orders/{order}/transport-summary', OrderTransportSummaryController::class)
        ->middleware('visibility.area:orders')
        ->name('orders.transport-summary');
    Route::middleware('visibility.area:load_board')->prefix('load-board')->name('load-board.')->group(function () {
        Route::get('/', [LoadBoardController::class, 'index'])->name('index');
        Route::get('/rows', [LoadBoardController::class, 'rows'])->name('rows');
        Route::get('/cases/{post}', [LoadBoardController::class, 'show'])->name('cases.show');
        Route::get('/{post}/advisor', [LoadBoardController::class, 'advisor'])->name('advisor');
        Route::get('/{post}/insights', [LoadBoardController::class, 'insights'])->name('insights');
        Route::post('/', [LoadBoardController::class, 'store'])->name('store');
        Route::post('/{post}/take', [LoadBoardController::class, 'take'])->name('take');
        Route::post('/{post}/release', [LoadBoardController::class, 'release'])->name('release');
        Route::patch('/{post}/buyer', [LoadBoardController::class, 'assignBuyer'])->name('buyer.update');
        Route::patch('/{post}/procurement-case/links', [LoadBoardController::class, 'attachProcurementCaseLink'])->name('procurement-case.links.attach');
        Route::post('/{post}/carrier-pool/candidates', [LoadBoardController::class, 'storeCarrierPoolCandidate'])->name('carrier-pool.candidates.store');
        Route::delete('/{post}/carrier-pool/candidates/{candidate}', [LoadBoardController::class, 'destroyCarrierPoolCandidate'])->name('carrier-pool.candidates.destroy');
        Route::post('/{post}/offers', [LoadBoardController::class, 'storeOffer'])->name('offers.store');
        Route::post('/{post}/offers/{offer}/select', [LoadBoardController::class, 'selectOffer'])->name('offers.select');
        Route::post('/{post}/offers/{offer}/approve', [LoadBoardController::class, 'approveOffer'])->name('offers.approve');
        Route::patch('/{post}/status', [LoadBoardController::class, 'updateStatus'])->name('status.update');
        Route::post('/{post}/ati/prepare', [LoadBoardController::class, 'prepareAti'])->name('ati.prepare');
    });
    Route::get('/disposition', [DispositionController::class, 'index'])->middleware('visibility.area:orders')->name('disposition.index');
    Route::post('/disposition/entries', [DispositionController::class, 'upsert'])->middleware('visibility.area:orders')->name('disposition.entries.upsert');

    Route::prefix('company-planning')->name('company-planning.')->middleware('company.planning')->group(function () {
        Route::get('/', [CompanyPlanningController::class, 'index'])->name('index');
        Route::post('/', [CompanyPlanningController::class, 'store'])->name('store');
        Route::get('/{initiative}', [CompanyPlanningController::class, 'show'])->name('show');
        Route::patch('/{initiative}', [CompanyPlanningController::class, 'update'])->name('update');
        Route::delete('/{initiative}', [CompanyPlanningController::class, 'destroy'])->name('destroy');
        Route::post('/{initiative}/milestones', [CompanyPlanningController::class, 'storeMilestone'])->name('milestones.store');
        Route::post('/{initiative}/milestones/reorder', [CompanyPlanningController::class, 'reorderMilestones'])->name('milestones.reorder');
        Route::patch('/milestones/{milestone}', [CompanyPlanningController::class, 'updateMilestone'])->name('milestones.update');
        Route::delete('/milestones/{milestone}', [CompanyPlanningController::class, 'destroyMilestone'])->name('milestones.destroy');
        Route::post('/milestones/{milestone}/spawn-task', [CompanyPlanningController::class, 'spawnTask'])->name('milestones.spawn-task');
        Route::post('/{initiative}/dependencies', [CompanyPlanningController::class, 'storeDependency'])->name('dependencies.store');
        Route::delete('/dependencies/{dependency}', [CompanyPlanningController::class, 'destroyDependency'])->name('dependencies.destroy');
    });
    Route::get('/pipeline', [PipelineController::class, 'index'])->middleware('visibility.area.any:pipeline|leads')->name('pipeline.index');
    Route::post('/pipeline/orders/{order}/accounting-handoff', [PipelineController::class, 'markAccountingHandoff'])
        ->middleware('visibility.area:pipeline')
        ->name('pipeline.orders.accounting-handoff');
    Route::post('/orders/intake/extract', [OrderIntakeController::class, 'extract'])
        ->middleware(['visibility.area:orders', 'throttle:order-intake'])
        ->name('orders.intake.extract');
    Route::get('/orders/intake/drafts', [OrderIntakeController::class, 'drafts'])
        ->middleware('visibility.area:orders')
        ->name('orders.intake.drafts');
    Route::get('/orders/intake/drafts/{draft}', [OrderIntakeController::class, 'show'])
        ->whereNumber('draft')
        ->middleware('visibility.area:orders')
        ->name('orders.intake.draft');
    Route::post('/orders/intake/drafts/{draft}/activate-learning', [OrderIntakeController::class, 'activateLearning'])
        ->whereNumber('draft')
        ->middleware('visibility.area:orders')
        ->name('orders.intake.learning.activate');
    Route::post('/orders/intake/drafts/{draft}/discard-learning', [OrderIntakeController::class, 'discardLearning'])
        ->whereNumber('draft')
        ->middleware('visibility.area:orders')
        ->name('orders.intake.learning.discard');
    Route::controller(OrderWizardController::class)->middleware('visibility.area:orders')->group(function () {
        Route::get('/orders/suggest-order-number', 'suggestOrderNumber')->name('orders.suggest-order-number');
        Route::get('/orders/create', 'create')->name('orders.create');
        Route::post('/orders', 'store')->name('orders.store');
        Route::post('/orders/calculate-compensation', 'calculateCompensation')->name('orders.calculate-compensation');
        Route::get('/orders-suggest/address', 'suggestAddress')->name('orders.suggest-address');
        Route::post('/orders/contractors', 'storeContractor')->name('orders.contractors.store');

        Route::whereNumber('order')->group(function () {
            Route::get('/orders/{order}/edit', 'edit')->name('orders.edit');
            Route::get('/orders/{order}/lead-precalculation-snapshot/document', 'leadPrecalculationSnapshotDocument')
                ->name('orders.lead-precalculation-snapshot.document');
            Route::post('/orders/{order}/save', 'update')->name('orders.save');
            Route::match(['patch', 'post'], '/orders/{order}', 'update')->name('orders.update');
            Route::post('/orders/{order}/portal-invites/carrier', [OrderPortalInviteController::class, 'storeCarrier'])
                ->name('orders.portal-invites.carrier.store');
            Route::post('/orders/{order}/portal-invites/customer', [OrderPortalInviteController::class, 'storeCustomer'])
                ->name('orders.portal-invites.customer.store');
            Route::get('/orders/{order}/templates/{printFormTemplate}/draft', 'generateDocumentDraft')->name('orders.templates.generate-draft');
            Route::match(['patch', 'post'], '/orders/{order}/inline', 'inlineUpdate')->name('orders.inline-update');
            Route::post('/orders/{order}/basic-terms/promote-to-contractor', [OrderBasicTermsController::class, 'promoteToContractor'])
                ->name('orders.basic-terms.promote');
            Route::delete('/orders/{order}', 'destroy')->withTrashed()->name('orders.destroy');
        });
    });
    Route::get('/orders/{order}/documents/list', [OrderDocumentsModalController::class, 'index'])
        ->middleware('visibility.area:orders')
        ->name('orders.documents.list');
    Route::get('/orders/{order}/activity-timeline', [ActivityTimelineController::class, 'showForOrder'])
        ->middleware('visibility.area:orders')
        ->name('orders.activity-timeline');

    Route::controller(OrderDocumentWorkflowController::class)->middleware('visibility.area:orders')->group(function () {
        Route::post('/orders/{order}/documents/from-template', 'storeFromTemplate')->name('orders.documents.from-template');
        Route::post('/orders/{order}/documents/{orderDocument}/request-approval', 'requestApproval')->name('orders.documents.request-approval');
        Route::post('/orders/{order}/documents/{orderDocument}/approve', 'approve')->name('orders.documents.approve');
        Route::post('/orders/{order}/documents/{orderDocument}/reject', 'reject')->name('orders.documents.reject');
        Route::post('/orders/{order}/documents/{orderDocument}/finalize', 'finalize')->name('orders.documents.finalize');
        Route::post('/orders/{order}/documents/{orderDocument}/regenerate-draft', 'regenerateDraft')->name('orders.documents.regenerate-draft');
        Route::delete('/orders/{order}/documents/{orderDocument}/print-workflow', 'discardPrintWorkflow')->name('orders.documents.discard-print-workflow');
        Route::get('/orders/{order}/documents/{orderDocument}/preview-draft', 'previewDraft')->name('orders.documents.preview-draft');
        Route::get('/orders/{order}/documents/{orderDocument}/preview-uploaded', 'previewUploaded')->name('orders.documents.preview-uploaded');
        Route::get('/orders/{order}/documents/{orderDocument}/overlay-assets/{overlayKey}', 'overlayAsset')->name('orders.documents.overlay-asset');
        Route::post('/orders/{order}/documents/{orderDocument}/overlay-positions', 'updateOverlayPositions')->name('orders.documents.update-overlay-positions');
        Route::get('/orders/{order}/documents/{orderDocument}/download-draft', 'downloadDraft')->name('orders.documents.download-draft');
        Route::get('/orders/{order}/documents/{orderDocument}/download-final', 'downloadFinal')->name('orders.documents.download-final');
        Route::post('/orders/{order}/documents/{orderDocument}/send-email', 'sendByEmail')->name('orders.documents.send-email');
    });

    Route::controller(UserManagementController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/users', 'index')->name('settings.users.index');
        Route::post('/users', 'store')->name('users.store');
        Route::patch('/users/{user}', 'update')->name('users.update');
        Route::delete('/users/{user}', 'destroy')->name('users.destroy');
    });

    Route::controller(RoleManagementController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/roles', 'index')->name('settings.roles.index');
        Route::post('/roles', 'store')->name('roles.store');
        Route::patch('/roles/{role}', 'update')->name('roles.update');
        Route::delete('/roles/{role}', 'destroy')->name('roles.destroy');
    });

    Route::middleware('visibility.area:settings_system')->controller(SettingsAiAnalyticsController::class)->group(function () {
        Route::get('/settings/ai-analytics', 'index')->name('settings.ai-analytics');
        Route::delete('/settings/ai-analytics/sales-book-gaps/{event}', 'dismissSalesBookGap')
            ->whereNumber('event')
            ->name('settings.ai-analytics.sales-book-gaps.dismiss');
    });

    Route::controller(SettingsSystemController::class)->middleware('visibility.area:settings_system')->group(function () {
        Route::get('/settings/system', 'index')->name('settings.system.index');
        Route::get('/settings/system/order-numbering', 'orderNumbering')->name('settings.system.order-numbering');
        Route::post('/settings/system/order-numbering', 'store')->name('settings.system.order-numbering.store');
        Route::post('/settings/system/order-numbering/preview', 'preview')->name('settings.system.order-numbering.preview');
        Route::patch('/settings/system/order-numbering/{orderNumberingRule}', 'update')->name('settings.system.order-numbering.update');
        Route::delete('/settings/system/order-numbering/{orderNumberingRule}', 'destroy')->name('settings.system.order-numbering.destroy');
    });

    Route::controller(SettingsTableManagementController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/tables', 'index')->name('settings.tables.index');
        Route::patch('/settings/tables/{role}', 'update')->name('settings.tables.update');
    });

    Route::get('/settings/motivation', [SettingsController::class, 'motivation'])
        ->middleware('visibility.area:settings')
        ->name('settings.motivation.index');

    Route::controller(SettingsMcpIntegrationController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/mcp-integrations', 'index')->name('settings.mcp-integrations.index');
        Route::put('/settings/mcp-integrations', 'update')->name('settings.mcp-integrations.update');
    });

    Route::controller(SettingsTemplateController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/templates', 'index')->name('settings.templates.index');
        Route::put('/settings/templates/basic-terms', 'updateBasicTerms')->name('settings.templates.basic-terms.update');
        Route::post('/settings/templates', 'store')->name('settings.templates.store');
        Route::patch('/settings/templates/{printFormTemplate}', 'update')->name('settings.templates.update');
        Route::delete('/settings/templates/{printFormTemplate}', 'destroy')->name('settings.templates.destroy');
        Route::get('/settings/templates/{printFormTemplate}/overlay-assets/{overlayKey}', 'overlayAsset')->name('settings.templates.overlay-asset');
        Route::get('/settings/templates/{printFormTemplate}/generate-order-draft', 'generateOrderDraft')->name('settings.templates.generate-order-draft');
        Route::get('/settings/templates/{printFormTemplate}/generate-lead-draft', 'generateLeadDraft')->name('settings.templates.generate-lead-draft');
    });

    Route::controller(SettingsBusinessProcessController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/business-processes', 'index')->name('settings.business-processes.index');
        Route::post('/settings/business-processes', 'storeProcess')->name('settings.business-processes.store');
        Route::patch('/settings/business-processes/{businessProcess}', 'updateProcess')->name('settings.business-processes.update');
        Route::delete('/settings/business-processes/{businessProcess}', 'destroyProcess')->name('settings.business-processes.destroy');
        Route::post('/settings/business-processes/{businessProcess}/stages', 'storeStage')->name('settings.business-processes.stages.store');
        Route::patch('/settings/business-processes/{businessProcess}/stages/{stage}', 'updateStage')->name('settings.business-processes.stages.update');
        Route::delete('/settings/business-processes/{businessProcess}/stages/{stage}', 'destroyStage')->name('settings.business-processes.stages.destroy');
    });

    Route::controller(SettingsDictionariesController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/dictionaries', 'index')->name('settings.dictionaries.index');
        Route::post('/settings/dictionaries/activity-types', 'storeActivityType')->name('settings.dictionaries.activity-types.store');
        Route::delete('/settings/dictionaries/activity-types/{contractorActivityType}', 'destroyActivityType')->name('settings.dictionaries.activity-types.destroy');
        Route::post('/settings/dictionaries/currencies', 'storeCurrency')->name('settings.dictionaries.currencies.store');
        Route::delete('/settings/dictionaries/currencies/{currency}', 'destroyCurrency')->name('settings.dictionaries.currencies.destroy');
        Route::post('/settings/dictionaries/vat-rates', 'storeVatRate')->name('settings.dictionaries.vat-rates.store');
        Route::delete('/settings/dictionaries/vat-rates/{vatRate}', 'destroyVatRate')->name('settings.dictionaries.vat-rates.destroy');
        Route::post('/settings/dictionaries/departments', 'storeDepartment')->name('settings.dictionaries.departments.store');
        Route::patch('/settings/dictionaries/departments/{department}', 'updateDepartment')->name('settings.dictionaries.departments.update');
        Route::delete('/settings/dictionaries/departments/{department}', 'destroyDepartment')->name('settings.dictionaries.departments.destroy');
    });

    Route::controller(SettingsKpiController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/motivation/kpi', 'index')->name('settings.motivation.kpi');
        Route::patch('/settings/motivation/kpi', 'update')->name('settings.motivation.kpi.update');
        Route::post('/settings/motivation/kpi/rules', 'storeDeductionRule')->name('settings.motivation.kpi.rules.store');
        Route::patch('/settings/motivation/kpi/rules/{kpiDeductionRule}', 'updateDeductionRule')->name('settings.motivation.kpi.rules.update');
        Route::delete('/settings/motivation/kpi/rules/{kpiDeductionRule}', 'destroyDeductionRule')->name('settings.motivation.kpi.rules.destroy');
        Route::get('/settings/motivation/salary', 'salaryIndex')->name('settings.motivation.salary');
        Route::post('/settings/motivation/salary/coefficients', 'storeSalaryCoefficient')->name('settings.motivation.salary.store');
        Route::patch('/settings/motivation/salary/coefficients/{salaryCoefficient}', 'updateSalaryCoefficient')->name('settings.motivation.salary.update');
        Route::delete('/settings/motivation/salary/coefficients/{salaryCoefficient}', 'destroySalaryCoefficient')->name('settings.motivation.salary.destroy');
    });

    Route::controller(ContractorController::class)->middleware('visibility.area:contractors')->group(function () {
        Route::get('/contractors', 'index')->name('contractors.index');
        Route::get('/contractors/create', 'create')->name('contractors.create');
        Route::post('/contractors', 'store')->name('contractors.store');
        Route::get('/contractors/duplicate-check', 'checkDuplicate')->name('contractors.duplicate-check');
        Route::get('/contractors-suggest/party', 'suggestParty')->name('contractors.suggest-party');
        Route::get('/contractors-suggest/address', 'suggestAddress')->name('contractors.suggest-address');
        Route::get('/contractors-suggest/bank', 'suggestBank')->name('contractors.suggest-bank');
        Route::post('/contractors/activity-types', 'storeActivityType')->name('contractors.activity-types.store');
        Route::post('/contractors/mass-update-owner', 'massUpdateOwner')->name('contractors.mass-update-owner');
        Route::get('/contractors/{contractor}', 'show')->name('contractors.show');
        Route::get('/contractors/{contractor}/partner-card', 'downloadPartnerCard')->name('contractors.partner-card');
        Route::get('/contractors/{contractor}/documents/{contractorDocument}/preview', 'previewDocument')->name('contractors.documents.preview');
        Route::get('/contractors/{contractor}/scoring', 'scoring')->name('contractors.scoring');
        Route::post('/contractors/{contractor}/risk-assessment/confirm', 'confirmRiskAssessment')->name('contractors.risk-assessment.confirm');
        Route::post('/contractors/{contractor}/limit-approval/request', 'requestLimitApproval')->name('contractors.limit-approval.request');
        Route::get('/contractors/{contractor}/edit', 'edit')->name('contractors.edit');
        Route::patch('/contractors/{contractor}', 'update')->name('contractors.update');
        Route::delete('/contractors/{contractor}', 'destroy')->name('contractors.destroy');
        Route::patch('/contractors/{contractor}/portrait', [ContractorPortraitController::class, 'update'])->name('contractors.portrait.update');
        Route::post('/contractors/{contractor}/contacts/{contact}/traklo/primary', [ContractorContactTrakloController::class, 'setPrimary'])->name('contractors.contacts.traklo.primary');
        Route::post('/contractors/{contractor}/contacts/{contact}/traklo/invite', [ContractorContactTrakloController::class, 'invite'])->name('contractors.contacts.traklo.invite');
        Route::post('/contractors/{contractor}/portrait-interactions', [ContractorPortraitController::class, 'storeInteraction'])->name('contractors.portrait-interactions.store');
        Route::post('/contractors/{contractor}/insight-drafts/from-mail/{mailMessage}', [ContractorInsightDraftController::class, 'extractFromMail'])->name('contractors.insight-drafts.extract-mail');
        Route::post('/contractors/{contractor}/insight-drafts/{insightDraft}/accept', [ContractorInsightDraftController::class, 'accept'])->name('contractors.insight-drafts.accept');
        Route::post('/contractors/{contractor}/insight-drafts/{insightDraft}/reject', [ContractorInsightDraftController::class, 'reject'])->name('contractors.insight-drafts.reject');
        Route::put('/contractors/{contractor}/print-form/basic-terms', [ContractorPrintFormController::class, 'updateBasicTerms'])->name('contractors.print-form.basic-terms.update');
        Route::post('/contractors/{contractor}/print-form/changes', [ContractorPrintFormController::class, 'submitChange'])->name('contractors.print-form.changes.submit');
        Route::post('/contractors/{contractor}/print-form/changes/{printFormChange}/resolve', [ContractorPrintFormController::class, 'resolveChange'])->name('contractors.print-form.changes.resolve');
    });

    Route::controller(ContractorController::class)
        ->middleware('visibility.area.any:contractors|orders')
        ->group(function () {
            Route::get('/contractors-search', 'search')->name('contractors.search');
        });

    Route::get('/fleet/containers', function () {
        return Inertia::render('Fleet/Containers');
    })->middleware('visibility.area:drivers')->name('fleet.containers.index');

    Route::controller(FleetVehicleController::class)->middleware('visibility.area:drivers')->group(function () {
        Route::get('/fleet/vehicles', 'index')->name('fleet.vehicles.index');
        Route::post('/fleet/vehicles', 'store')->name('fleet.vehicles.store');
        Route::get('/fleet/vehicles/{fleetVehicle}', 'show')->name('fleet.vehicles.show');
        Route::patch('/fleet/vehicles/{fleetVehicle}', 'update')->name('fleet.vehicles.update');
        Route::post('/fleet/vehicles/{fleetVehicle}/documents', 'storeDocument')->name('fleet.vehicles.documents.store');
        Route::delete('/fleet/vehicles/{fleetVehicle}/documents/{fleetVehicleDocument}', 'destroyDocument')->name('fleet.vehicles.documents.destroy');
        Route::get('/fleet/vehicles/{fleetVehicle}/documents/{fleetVehicleDocument}/download', 'downloadDocument')->name('fleet.vehicles.documents.download');
        Route::get('/fleet/vehicles/{fleetVehicle}/documents/{fleetVehicleDocument}/preview', 'previewDocument')->name('fleet.vehicles.documents.preview');
    });

    Route::controller(FleetDriverController::class)->middleware('visibility.area:drivers')->group(function () {
        Route::get('/drivers', 'index')->name('drivers.index');
        Route::post('/fleet/drivers', 'store')->name('fleet.drivers.store');
        Route::get('/fleet/drivers/{fleetDriver}', 'show')->name('fleet.drivers.show');
        Route::patch('/fleet/drivers/{fleetDriver}', 'update')->name('fleet.drivers.update');
        Route::post('/fleet/drivers/{fleetDriver}/documents', 'storeDocument')->name('fleet.drivers.documents.store');
        Route::delete('/fleet/drivers/{fleetDriver}/documents/{fleetDriverDocument}', 'destroyDocument')->name('fleet.drivers.documents.destroy');
        Route::get('/fleet/drivers/{fleetDriver}/documents/{fleetDriverDocument}/download', 'downloadDocument')->name('fleet.drivers.documents.download');
        Route::get('/fleet/drivers/{fleetDriver}/documents/{fleetDriverDocument}/preview', 'previewDocument')->name('fleet.drivers.documents.preview');
    });

    Route::get('/fleet/options/vehicles', [FleetVehicleController::class, 'optionsForOrder'])
        ->middleware('visibility.area:orders')
        ->name('fleet.options.vehicles');

    Route::controller(FleetTripController::class)->middleware('visibility.area.any:fleet_trips|own_fleet|drivers')->group(function () {
        Route::get('/fleet/trips', 'index')->name('fleet.trips.index');
        Route::post('/fleet/trips', 'store')->name('fleet.trips.store');
        Route::get('/fleet/trips/{fleetTrip}', 'show')->name('fleet.trips.show');
        Route::patch('/fleet/trips/{fleetTrip}', 'update')->name('fleet.trips.update');
        Route::post('/fleet/trips/{fleetTrip}/complete', 'complete')->name('fleet.trips.complete');
    });

    Route::get('/fleet/efficiency', [FleetEfficiencyController::class, 'index'])
        ->middleware('visibility.area.any:fleet_efficiency|own_fleet|drivers')
        ->name('fleet.efficiency.index');

    Route::get('/fleet/options/drivers', [FleetDriverController::class, 'optionsForOrder'])
        ->middleware('visibility.area:orders')
        ->name('fleet.options.drivers');

    Route::get('/finance', FinanceIndexController::class)->middleware('visibility.area.any:documents|payment_schedules|finance_salary')->name('finance.index');
    Route::get('/finance/reconciliation', [FinanceReconciliationController::class, 'index'])
        ->middleware('visibility.area:payment_schedules')
        ->name('finance.reconciliation.index');
    Route::post('/finance/reconciliation', [FinanceReconciliationController::class, 'store'])
        ->middleware('visibility.area:payment_schedules')
        ->name('finance.reconciliation.store');
    Route::get('/finance/management-accounting', [ManagementAccountingController::class, 'index'])
        ->name('finance.management-accounting.index');
    Route::post('/finance/management-accounting/imports', [ManagementAccountingImportController::class, 'store'])
        ->name('finance.management-accounting.imports.store');
    Route::get('/finance/management-accounting/imports/{import}', [ManagementAccountingImportController::class, 'show'])
        ->name('finance.management-accounting.imports.show');
    Route::delete('/finance/management-accounting/imports/{import}', [ManagementAccountingImportController::class, 'destroy'])
        ->name('finance.management-accounting.imports.destroy');
    Route::get('/finance/management-accounting/lines/{line}/operational-candidates', [ManagementAccountingImportController::class, 'operationalCandidates'])
        ->name('finance.management-accounting.lines.operational-candidates');
    Route::post('/finance/management-accounting/lines/{line}/allocate', [ManagementAccountingImportController::class, 'allocate'])
        ->name('finance.management-accounting.lines.allocate');
    Route::post('/finance/management-accounting/lines/{line}/deallocate', [ManagementAccountingImportController::class, 'deallocate'])
        ->name('finance.management-accounting.lines.deallocate');
    Route::post('/finance/management-accounting/manual-entries', [ManagementAccountingImportController::class, 'storeManual'])
        ->name('finance.management-accounting.manual-entries.store');
    Route::post('/finance/management-accounting/categories', [ManagementAccountingImportController::class, 'storeCategory'])
        ->name('finance.management-accounting.categories.store');
    Route::post('/finance/management-accounting/categories/sync', [ManagementAccountingImportController::class, 'syncCategories'])
        ->name('finance.management-accounting.categories.sync');
    Route::patch('/finance/management-accounting/categories/{category}', [ManagementAccountingImportController::class, 'updateCategory'])
        ->name('finance.management-accounting.categories.update');
    Route::delete('/finance/management-accounting/categories/{category}', [ManagementAccountingImportController::class, 'destroyCategory'])
        ->name('finance.management-accounting.categories.destroy');
    Route::get('/budgeting', [BudgetingController::class, 'index'])->name('budgeting.index');
    Route::patch('/budgeting/scenario', [BudgetingController::class, 'updateScenario'])->name('budgeting.scenario.update');
    Route::patch('/budgeting/sales-targets', [BudgetingController::class, 'updateSalesTargets'])->name('budgeting.sales-targets.update');
    Route::post('/budgeting/opex-articles', [BudgetingController::class, 'storeOpexArticle'])->name('budgeting.opex-articles.store');
    Route::patch('/budgeting/opex-articles/{opexArticle}', [BudgetingController::class, 'updateOpexArticle'])->name('budgeting.opex-articles.update');
    Route::delete('/budgeting/opex-articles/{opexArticle}', [BudgetingController::class, 'destroyOpexArticle'])->name('budgeting.opex-articles.destroy');
    Route::post('/budgeting/plan-snapshots', [BudgetingController::class, 'freezePlan'])->name('budgeting.plan-snapshots.store');
    Route::get('/documents', [DocumentRegistryController::class, 'index'])->middleware('visibility.area.any:documents|orders')->name('documents.index');
    Route::post('/documents', [DocumentRegistryController::class, 'store'])->middleware('visibility.area.any:documents|orders')->name('documents.store');
    Route::post('/documents/optimize-pdf', DocumentOptimizeController::class)->middleware('visibility.area.any:documents|orders')->name('documents.optimize-pdf');
    Route::post('/documents/estimate-upload-budget', DocumentUploadBudgetEstimateController::class)->middleware('visibility.area.any:documents|orders')->name('documents.estimate-upload-budget');
    Route::patch('/documents/{document}', [DocumentRegistryController::class, 'update'])->middleware('visibility.area.any:documents|orders')->name('documents.update');
    Route::patch('/documents/orders/{order}/entered-in-1c', [DocumentRegistryController::class, 'updateEnteredIn1C'])->middleware('visibility.area.any:documents|orders')->name('documents.orders.entered-in-1c');
    Route::patch('/documents/orders/{order}/track-received', [DocumentRegistryController::class, 'updateTrackReceived'])->middleware('visibility.area.any:documents|orders')->name('documents.orders.track-received');
    Route::patch('/documents/orders/{order}/edo-acknowledgement', [DocumentRegistryController::class, 'updateEdoAcknowledgement'])->middleware('visibility.area.any:documents|orders')->name('documents.orders.edo-acknowledgement');
    Route::delete('/documents/{document}', [DocumentRegistryController::class, 'destroy'])->middleware('visibility.area.any:documents|orders')->name('documents.destroy');
    Route::post('/finance/documents', [FinanceDocumentController::class, 'store'])->middleware('visibility.area:documents')->name('finance.documents.store');
    Route::patch('/finance/documents/{financeDocument}', [FinanceDocumentController::class, 'update'])->middleware('visibility.area:documents')->name('finance.documents.update');
    Route::controller(SettingsKpiController::class)->middleware('visibility.area:finance_salary')->group(function () {
        Route::get('/finance/salary', 'financeSalaryIndex')->name('finance.salary.index');
        Route::post('/finance/salary/periods', 'storeSalaryPeriod')->name('finance.salary.periods.store');
        Route::delete('/finance/salary/periods/{salaryPeriod}', 'destroySalaryPeriod')->name('finance.salary.periods.destroy');
        Route::post('/finance/salary/periods/{salaryPeriod}/recalculate', 'recalculateSalaryPeriod')->name('finance.salary.periods.recalculate');
        Route::post('/finance/salary/periods/{salaryPeriod}/approve', 'approveSalaryPeriod')->name('finance.salary.periods.approve');
        Route::post('/finance/salary/periods/{salaryPeriod}/close', 'closeSalaryPeriod')->name('finance.salary.periods.close');
        Route::post('/finance/salary/periods/{salaryPeriod}/payouts', 'storeSalaryPayout')->name('finance.salary.periods.payouts.store');
        Route::post('/finance/salary/advance-payouts', 'storeSalaryAdvanceWithoutPeriod')->name('finance.salary.advance-payouts.store');
        Route::post('/finance/salary/coefficients', 'storeSalaryCoefficient')->name('finance.salary.coefficients.store');
        Route::patch('/finance/salary/coefficients/{salaryCoefficient}', 'updateSalaryCoefficient')->name('finance.salary.coefficients.update');
        Route::delete('/finance/salary/coefficients/{salaryCoefficient}', 'destroySalaryCoefficient')->name('finance.salary.coefficients.destroy');
    });

    Route::controller(TaskController::class)->group(function () {
        Route::middleware('visibility.area:tasks')->group(function () {
            Route::get('/tasks', 'index')->name('tasks.index');
            Route::post('/tasks', 'store')->name('tasks.store');
            Route::post('/tasks/bulk', 'bulkUpdate')->name('tasks.bulk');
            Route::get('/tasks/{task}', 'show')->name('tasks.show');
            Route::patch('/tasks/{task}', 'update')->name('tasks.update');
            Route::patch('/tasks/{task}/inline', 'inlineUpdate')->name('tasks.inline-update');
            Route::patch('/tasks/{task}/due', 'updateDue')->name('tasks.due.update');
            Route::delete('/tasks/{task}', 'destroy')->name('tasks.destroy');
            Route::post('/tasks/{task}/complete-and-follow-up', 'completeAndCreateFollowUp')->name('tasks.complete-and-follow-up');
            Route::post('/tasks/{task}/checklist-items', 'storeChecklistItem')->name('tasks.checklist-items.store');
            Route::patch('/tasks/{task}/checklist-items/{taskChecklistItem}', 'toggleChecklistItem')->name('tasks.checklist-items.toggle');
            Route::post('/tasks/{task}/comments', 'storeComment')->name('tasks.comments.store');
            Route::post('/tasks/{task}/attachments', 'storeAttachment')->name('tasks.attachments.store');
            Route::delete('/tasks/{task}/attachments/{taskAttachment}', 'destroyAttachment')->name('tasks.attachments.destroy');
        });

        Route::middleware('visibility.area.any:tasks|kanban')->group(function () {
            Route::get('/tasks/{task}/attachments/{taskAttachment}/download', 'downloadAttachment')->name('tasks.attachments.download');
            Route::patch('/tasks/{task}/status', 'updateStatus')->name('tasks.status.update');
        });

        Route::get('/kanban', 'kanban')
            ->middleware('visibility.area:kanban')
            ->name('kanban.index');
    });

    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])
        ->middleware('visibility.area:leads')
        ->name('leads.status.update');

    Route::patch('/leads/{lead}/grid-field', [LeadController::class, 'updateGridField'])
        ->middleware('visibility.area:leads')
        ->name('leads.grid-field.update');

    Route::get('/modules', fn () => Inertia::render('Modules/Index'))
        ->middleware('visibility.area.any:modules_how_much_fits|modules_how_much_costs|modules_import_cost|modules_proposal_templates|modules')
        ->name('modules.index');

    Route::redirect('/modules/counter', '/sales-assistant/counter')
        ->middleware('visibility.area:sales_assistant_counter');

    Route::middleware('visibility.area:modules_how_much_fits')->group(function () {
        Route::controller(LoadingPlannerController::class)->prefix('modules/how-much-fits')->name('modules.how-much-fits.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/projects', 'storeProject')->name('projects.store');
            Route::patch('/projects/{loadingPlannerProject}', 'updateProject')->name('projects.update');
            Route::delete('/projects/{loadingPlannerProject}', 'destroyProject')->name('projects.destroy');
            Route::post('/transport-templates', 'storeTransportTemplate')->name('transport-templates.store');
            Route::patch('/transport-templates/{transportTemplate}', 'updateTransportTemplate')->name('transport-templates.update');
            Route::delete('/transport-templates/{transportTemplate}', 'destroyTransportTemplate')->name('transport-templates.destroy');
        });
    });

    Route::get('/modules/how-much-costs', fn () => Inertia::render('Modules/HowMuchCosts'))
        ->middleware('visibility.area:modules_how_much_costs')
        ->name('modules.how-much-costs.index');

    Route::middleware('visibility.area:modules_import_cost')->group(function () {
        Route::controller(ImportCostCalculatorController::class)->prefix('modules/import-cost')->name('modules.import-cost.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/tn-ved/search', 'searchTnVed')->name('tn-ved.search');
            Route::post('/calculate', 'calculate')->name('calculate');
        });
    });

    Route::middleware('visibility.area:modules_proposal_templates')->prefix('modules/proposal-templates')->name('modules.proposal-templates.')->controller(ProposalHtmlTemplateController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{proposalHtmlTemplate}/edit', 'edit')->name('edit');
        Route::patch('/{proposalHtmlTemplate}', 'update')->name('update');
        Route::get('/{proposalHtmlTemplate}/preview/{lead}', 'preview')->name('preview');
    });

    Route::get('/settings', SettingsController::class)->middleware('visibility.area:settings')->name('settings.index');

    Route::get('/users', fn () => redirect('/settings/users'));
    Route::get('/roles', fn () => redirect('/settings/roles'));

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/ui-preferences', [ProfileController::class, 'updateUiPreferences'])->name('profile.ui-preferences');
    Route::patch('/profile/mobile-bottom-nav', [ProfileController::class, 'updateMobileBottomNav'])->name('profile.mobile-bottom-nav');
    Route::patch('/profile/sidebar-favorites', [ProfileController::class, 'updateSidebarFavorites'])->name('profile.sidebar-favorites');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('grid-views')->name('grid-views.')->group(function () {
        Route::get('/', [GridViewController::class, 'index'])->name('index');
        Route::post('/', [GridViewController::class, 'store'])->name('store');
        Route::get('/{gridView}', [GridViewController::class, 'show'])->name('show');
        Route::patch('/{gridView}', [GridViewController::class, 'update'])->name('update');
        Route::delete('/{gridView}', [GridViewController::class, 'destroy'])->name('destroy');
    });

    // Payment Schedule Routes
    Route::prefix('payment-schedules')->name('payment-schedules.')->middleware('visibility.area.any:documents|payment_schedules|finance_salary')->group(function () {
        Route::patch('/payment-run', [PaymentScheduleController::class, 'updatePaymentRun'])->name('payment-run');
        Route::post('/{paymentSchedule}/record-payment', [PaymentScheduleController::class, 'recordPayment'])->name('record-payment');
        Route::patch('/{paymentSchedule}/invoice-number', [PaymentScheduleController::class, 'updateInvoiceNumber'])->name('invoice-number');
        Route::get('/{paymentSchedule}/partial-payments', [PaymentScheduleController::class, 'getPartialPayments'])->name('partial-payments');
        Route::get('/{paymentSchedule}/payment-events', [PaymentScheduleController::class, 'paymentEvents'])->name('payment-events');
        Route::post('/payment-events/{paymentEvent}/void', [PaymentScheduleController::class, 'voidPaymentEvent'])->name('payment-events.void');
        Route::post('/{paymentSchedule}/cancel', [PaymentScheduleController::class, 'cancel'])->name('cancel');
        Route::post('/{paymentSchedule}/restore', [PaymentScheduleController::class, 'restore'])->name('restore');
    });

    Route::prefix('messenger')->name('messenger.')->group(function () {
        Route::get('/unread-count', [MessengerController::class, 'unreadCount'])->name('unread-count');
        Route::get('/colleagues', [MessengerController::class, 'colleagues'])->name('colleagues');
        Route::get('/counterparty-contacts', [MessengerController::class, 'counterpartyContacts'])->name('counterparty-contacts');
        Route::get('/document-chips', [MessengerController::class, 'documentChips'])->name('document-chips');
        Route::get('/conversations', [MessengerController::class, 'conversations'])->name('conversations.index');
        Route::post('/conversations/open', [MessengerController::class, 'openDirect'])->name('conversations.open');
        Route::post('/conversations/open-counterparty', [MessengerController::class, 'openCounterparty'])->name('conversations.open-counterparty');
        Route::post('/conversations/groups', [MessengerController::class, 'storeGroup'])->name('conversations.groups.store');
        Route::get('/conversations/{conversation}/counterparty-orders', [MessengerController::class, 'counterpartyOrders'])->name('conversations.counterparty-orders');
        Route::get('/conversations/{conversation}/messages', [MessengerController::class, 'messages'])->name('conversations.messages');
        Route::post('/conversations/{conversation}/messages', [MessengerController::class, 'storeMessage'])->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/read', [MessengerController::class, 'markRead'])->name('conversations.read');
    });

    Route::get('/mobile/messenger', fn () => Inertia::render('Mobile/Messenger'))->name('mobile.messenger.app');

    Route::prefix('mobile/shell')->name('mobile.shell.')->group(function (): void {
        Route::get('/tasks', [MobileShellController::class, 'tasks'])->name('tasks');
        Route::get('/orders', [MobileShellController::class, 'orders'])->name('orders');
        Route::get('/documents', [MobileShellController::class, 'documents'])->name('documents');
        Route::get('/documents/contractors', [MobileShellController::class, 'documentContractors'])->name('documents.contractors');
        Route::get('/documents/contractors/{contractor}/orders', [MobileShellController::class, 'documentContractorOrders'])->name('documents.contractor-orders');
        Route::get('/documents/orders/{order}/checklist', [MobileShellController::class, 'orderDocumentChecklist'])->name('documents.order-checklist');
        Route::get('/traklo-leads', [MobileShellController::class, 'trakloLeads'])->name('traklo-leads');
        Route::post('/leads/from-text', [MobileShellController::class, 'createLeadFromText'])->name('leads.from-text');
        Route::patch('/leads/{lead}', [MobileShellController::class, 'updateLeadDraft'])->name('leads.update');
        Route::get('/entity-chips', [MobileShellController::class, 'entityChips'])->name('entity-chips');
        Route::get('/orders/{order}/document-slots', [MobileShellController::class, 'orderDocumentSlots'])->name('orders.document-slots');
        Route::get('/orders/{order}/summary', [MobileShellController::class, 'orderSummary'])->name('orders.summary');
        Route::get('/leads/{lead}/summary', [MobileShellController::class, 'leadSummary'])->name('leads.summary');
        Route::get('/contractors/{contractor}/summary', [MobileShellController::class, 'contractorSummary'])->name('contractors.summary');
        Route::get('/link-preview', [MobileShellController::class, 'linkPreview'])->name('link-preview');
    });

    Route::prefix('mobile/shell/counterparty')->name('mobile.shell.counterparty.')->group(function (): void {
        Route::get('/orders', [MobileCounterpartyShellController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}/summary', [MobileCounterpartyShellController::class, 'orderSummary'])->name('orders.summary');
        Route::get('/orders/{order}/document-slots', [MobileCounterpartyShellController::class, 'orderDocumentSlots'])->name('orders.document-slots');
        Route::post('/orders/{order}/documents', [MobileCounterpartyShellController::class, 'storeDocument'])->name('orders.documents.store');
    });

    Route::prefix('cabinet-notifications')->name('cabinet-notifications.')->group(function () {
        Route::get('/summary', [CabinetNotificationController::class, 'summary'])->name('summary');
        Route::get('/', [CabinetNotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [CabinetNotificationController::class, 'markRead'])->name('read');
        Route::post('/{notification}/unread', [CabinetNotificationController::class, 'markUnread'])->name('unread');
        Route::delete('/{notification}', [CabinetNotificationController::class, 'destroy'])->name('destroy');
        Route::post('/read-all', [CabinetNotificationController::class, 'markAllRead'])->name('read-all');
    });
});

Route::prefix('integrations')->group(function () {
    Route::post('/astral/epd/webhook', AstralEpdWebhookController::class)
        ->middleware('verify.astral.epd.signature')
        ->name('integrations.astral.epd.webhook');

    Route::middleware('verify.onec.token')->group(function () {
        Route::post('/1c-fresh/etrn/create-from-order', [OneCFreshEtrnController::class, 'createFromOrder'])
            ->name('integrations.onec-fresh.etrn.create-from-order');
        Route::get('/1c-fresh/etrn-journal', [OneCFreshEtrnController::class, 'journal'])
            ->name('integrations.onec-fresh.etrn-journal');
        Route::get('/1c-fresh/orders/{order}/etrn-documents', [OneCFreshEtrnController::class, 'index'])
            ->name('integrations.onec-fresh.orders.etrn-documents');
        Route::get('/1c-fresh/orders/{order}/etrn-latest-draft', [OneCFreshEtrnController::class, 'latestDraft'])
            ->name('integrations.onec-fresh.orders.etrn-latest-draft');
        Route::post('/1c-fresh/etrn-status', [OneCFreshEtrnController::class, 'pushStatus'])
            ->name('integrations.onec-fresh.etrn-status');
    });
});

require __DIR__.'/auth.php';
