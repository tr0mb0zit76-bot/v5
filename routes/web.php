<?php

use App\Http\Controllers\CabinetNotificationController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRegistryController;
use App\Http\Controllers\FinanceDocumentController;
use App\Http\Controllers\FinanceIndexController;
use App\Http\Controllers\FleetDriverController;
use App\Http\Controllers\FleetVehicleController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\Orders\OrderDocumentWorkflowController;
use App\Http\Controllers\Orders\OrderIndexController;
use App\Http\Controllers\Orders\OrderWizardController;
use App\Http\Controllers\PaymentScheduleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SalesAssistantController;
use App\Http\Controllers\SalesScriptController;
use App\Http\Controllers\SalesScriptEditorController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsDictionariesController;
use App\Http\Controllers\SettingsKpiController;
use App\Http\Controllers\SettingsTableManagementController;
use App\Http\Controllers\SettingsTemplateController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Use APP_URL host as CRM domain so local/prod can differ without route edits.
$crmDomain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm.log-sol.local';
$showcaseDomain = 'v5.local';

Route::domain($showcaseDomain)->controller(PublicSiteController::class)->group(function () {
    Route::get('/', 'home')->name('public.home');
    Route::get('/about', 'about')->name('public.about');
    Route::get('/services', 'services')->name('public.services');
    Route::get('/cases', 'cases')->name('public.cases');
    Route::get('/contacts', 'contacts')->name('public.contacts');
    Route::any('/_boost/browser-logs', fn () => response()->noContent())->name('public.boost.browser-logs');
});

Route::domain($showcaseDomain)->any('/{any}', function () use ($crmDomain) {
    $scheme = request()->isSecure() ? 'https' : 'http';
    $path = ltrim((string) request()->path(), '/');
    $queryString = request()->getQueryString();
    $target = sprintf('%s://%s/%s', $scheme, $crmDomain, $path);

    if (is_string($queryString) && $queryString !== '') {
        $target .= '?'.$queryString;
    }

    return redirect()->to($target);
})->where('any', '.*');

Route::domain($crmDomain)->get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('visibility.area:dashboard')->name('dashboard');

    Route::controller(LeadController::class)->middleware('visibility.area:leads')->group(function () {
        Route::get('/leads', 'index')->name('leads.index');
        Route::get('/leads/create', 'create')->name('leads.create');
        Route::post('/leads', 'store')->name('leads.store');
        Route::get('/leads/{lead}', 'show')->name('leads.show');
        Route::patch('/leads/{lead}', 'update')->name('leads.update');
        Route::delete('/leads/{lead}', 'destroy')->name('leads.destroy');
        Route::post('/leads/{lead}/proposal', 'prepareProposal')->name('leads.proposal');
        Route::post('/leads/{lead}/next-step', 'storeNextStep')->name('leads.next-step.store');
        Route::get('/leads/{lead}/templates/{printFormTemplate}/draft', 'generateCommercialDraft')->name('leads.templates.generate-draft');
        Route::post('/leads/{lead}/convert', 'convert')->name('leads.convert');
    });

    Route::middleware('visibility.area:scripts')->group(function () {
        Route::controller(SalesScriptController::class)->group(function () {
            Route::get('/scripts', 'index')->name('scripts.index');
            Route::post('/scripts/sessions', 'storeSession')->name('scripts.sessions.store');
            Route::get('/scripts/sessions/{sales_script_play_session}', 'showSession')->name('scripts.sessions.show');
            Route::post('/scripts/sessions/{sales_script_play_session}/advance', 'advance')->name('scripts.sessions.advance');
            Route::post('/scripts/sessions/{sales_script_play_session}/complete', 'complete')->name('scripts.sessions.complete');
        });

        Route::controller(SalesAssistantController::class)->prefix('sales-assistant')->name('sales-assistant.')->group(function () {
            Route::get('/book', 'book')->name('book');
            Route::get('/trainer', 'trainer')->name('trainer');
        });
    });

    Route::prefix('scripts/editor')
        ->name('scripts.editor.')
        ->middleware(['visibility.area:scripts', 'can.manage.sales.scripts'])
        ->group(function () {
            Route::get('/', [SalesScriptEditorController::class, 'index'])->name('index');
            Route::post('/scripts', [SalesScriptEditorController::class, 'storeScript'])->name('scripts.store');
            Route::patch('/scripts/{sales_script}', [SalesScriptEditorController::class, 'updateScript'])->name('scripts.update');
            Route::delete('/scripts/{sales_script}', [SalesScriptEditorController::class, 'destroyScript'])->name('scripts.destroy');
            Route::post('/scripts/{sales_script}/versions', [SalesScriptEditorController::class, 'storeVersion'])->name('scripts.versions.store');
            Route::get('/versions/{sales_script_version}', [SalesScriptEditorController::class, 'showVersion'])->name('versions.show');
            Route::patch('/versions/{sales_script_version}', [SalesScriptEditorController::class, 'updateVersion'])->name('versions.update');
            Route::post('/versions/{sales_script_version}/publish', [SalesScriptEditorController::class, 'publishVersion'])->name('versions.publish');
            Route::post('/versions/{sales_script_version}/unpublish', [SalesScriptEditorController::class, 'unpublishVersion'])->name('versions.unpublish');
            Route::post('/versions/{sales_script_version}/nodes', [SalesScriptEditorController::class, 'storeNode'])->name('versions.nodes.store');
            Route::post('/versions/{sales_script_version}/transitions', [SalesScriptEditorController::class, 'storeTransition'])->name('versions.transitions.store');
            Route::patch('/nodes/{sales_script_node}', [SalesScriptEditorController::class, 'updateNode'])->name('nodes.update');
            Route::delete('/nodes/{sales_script_node}', [SalesScriptEditorController::class, 'destroyNode'])->name('nodes.destroy');
            Route::patch('/transitions/{sales_script_transition}', [SalesScriptEditorController::class, 'updateTransition'])->name('transitions.update');
            Route::delete('/transitions/{sales_script_transition}', [SalesScriptEditorController::class, 'destroyTransition'])->name('transitions.destroy');
        });

    Route::get('/orders', OrderIndexController::class)->middleware('visibility.area:orders')->name('orders.index');
    Route::controller(OrderWizardController::class)->middleware('visibility.area:orders')->group(function () {
        Route::get('/orders/create', 'create')->name('orders.create');
        Route::post('/orders', 'store')->name('orders.store');
        Route::get('/orders/{order}/edit', 'edit')->name('orders.edit');
        Route::patch('/orders/{order}', 'update')->name('orders.update');
        Route::post('/orders/calculate-compensation', 'calculateCompensation')->name('orders.calculate-compensation');
        Route::get('/orders/{order}/templates/{printFormTemplate}/draft', 'generateDocumentDraft')->name('orders.templates.generate-draft');
        Route::patch('/orders/{order}/inline', 'inlineUpdate')->name('orders.inline-update');
        Route::delete('/orders/{order}', 'destroy')->withTrashed()->name('orders.destroy');
        Route::get('/orders-suggest/address', 'suggestAddress')->name('orders.suggest-address');
        Route::post('/orders/contractors', 'storeContractor')->name('orders.contractors.store');
    });
    Route::controller(OrderDocumentWorkflowController::class)->middleware('visibility.area:orders')->group(function () {
        Route::post('/orders/{order}/documents/from-template', 'storeFromTemplate')->name('orders.documents.from-template');
        Route::post('/orders/{order}/documents/{orderDocument}/request-approval', 'requestApproval')->name('orders.documents.request-approval');
        Route::post('/orders/{order}/documents/{orderDocument}/approve', 'approve')->name('orders.documents.approve');
        Route::post('/orders/{order}/documents/{orderDocument}/reject', 'reject')->name('orders.documents.reject');
        Route::post('/orders/{order}/documents/{orderDocument}/finalize', 'finalize')->name('orders.documents.finalize');
        Route::post('/orders/{order}/documents/{orderDocument}/regenerate-draft', 'regenerateDraft')->name('orders.documents.regenerate-draft');
        Route::delete('/orders/{order}/documents/{orderDocument}/print-workflow', 'discardPrintWorkflow')->name('orders.documents.discard-print-workflow');
        Route::get('/orders/{order}/documents/{orderDocument}/preview-draft', 'previewDraft')->name('orders.documents.preview-draft');
        Route::get('/orders/{order}/documents/{orderDocument}/download-draft', 'downloadDraft')->name('orders.documents.download-draft');
        Route::get('/orders/{order}/documents/{orderDocument}/download-final', 'downloadFinal')->name('orders.documents.download-final');
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

    Route::controller(SettingsTableManagementController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/tables', 'index')->name('settings.tables.index');
        Route::patch('/settings/tables/{role}', 'update')->name('settings.tables.update');
    });

    Route::get('/settings/motivation', [SettingsController::class, 'motivation'])
        ->middleware('visibility.area:settings')
        ->name('settings.motivation.index');

    Route::controller(SettingsTemplateController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/templates', 'index')->name('settings.templates.index');
        Route::post('/settings/templates', 'store')->name('settings.templates.store');
        Route::patch('/settings/templates/{printFormTemplate}', 'update')->name('settings.templates.update');
        Route::delete('/settings/templates/{printFormTemplate}', 'destroy')->name('settings.templates.destroy');
        Route::get('/settings/templates/{printFormTemplate}/generate-order-draft', 'generateOrderDraft')->name('settings.templates.generate-order-draft');
        Route::get('/settings/templates/{printFormTemplate}/generate-lead-draft', 'generateLeadDraft')->name('settings.templates.generate-lead-draft');
    });

    Route::controller(SettingsDictionariesController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/dictionaries', 'index')->name('settings.dictionaries.index');
        Route::post('/settings/dictionaries/activity-types', 'storeActivityType')->name('settings.dictionaries.activity-types.store');
        Route::delete('/settings/dictionaries/activity-types/{contractorActivityType}', 'destroyActivityType')->name('settings.dictionaries.activity-types.destroy');
    });

    Route::controller(SettingsKpiController::class)->middleware('visibility.area:settings')->group(function () {
        Route::get('/settings/motivation/kpi', 'index')->name('settings.motivation.kpi');
        Route::patch('/settings/motivation/kpi', 'update')->name('settings.motivation.kpi.update');
        Route::get('/settings/motivation/salary', 'salaryIndex')->name('settings.motivation.salary');
        Route::post('/settings/motivation/salary/coefficients', 'storeSalaryCoefficient')->name('settings.motivation.salary.store');
        Route::patch('/settings/motivation/salary/coefficients/{salaryCoefficient}', 'updateSalaryCoefficient')->name('settings.motivation.salary.update');
        Route::delete('/settings/motivation/salary/coefficients/{salaryCoefficient}', 'destroySalaryCoefficient')->name('settings.motivation.salary.destroy');
    });

    Route::controller(ContractorController::class)->middleware('visibility.area:contractors')->group(function () {
        Route::get('/contractors', 'index')->name('contractors.index');
        Route::get('/contractors/create', 'create')->name('contractors.create');
        Route::post('/contractors', 'store')->name('contractors.store');
        Route::get('/contractors/{contractor}', 'show')->name('contractors.show');
        Route::get('/contractors/{contractor}/scoring', 'scoring')->name('contractors.scoring');
        Route::get('/contractors/{contractor}/edit', 'edit')->name('contractors.edit');
        Route::patch('/contractors/{contractor}', 'update')->name('contractors.update');
        Route::delete('/contractors/{contractor}', 'destroy')->name('contractors.destroy');
        Route::post('/contractors/activity-types', 'storeActivityType')->name('contractors.activity-types.store');
        Route::post('/contractors/mass-update-owner', 'massUpdateOwner')->name('contractors.mass-update-owner');
        Route::get('/contractors-suggest/party', 'suggestParty')->name('contractors.suggest-party');
        Route::get('/contractors-suggest/address', 'suggestAddress')->name('contractors.suggest-address');
        Route::get('/contractors-suggest/bank', 'suggestBank')->name('contractors.suggest-bank');
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
    });

    Route::controller(FleetDriverController::class)->middleware('visibility.area:drivers')->group(function () {
        Route::get('/drivers', 'index')->name('drivers.index');
        Route::post('/fleet/drivers', 'store')->name('fleet.drivers.store');
        Route::get('/fleet/drivers/{fleetDriver}', 'show')->name('fleet.drivers.show');
        Route::patch('/fleet/drivers/{fleetDriver}', 'update')->name('fleet.drivers.update');
        Route::post('/fleet/drivers/{fleetDriver}/documents', 'storeDocument')->name('fleet.drivers.documents.store');
        Route::delete('/fleet/drivers/{fleetDriver}/documents/{fleetDriverDocument}', 'destroyDocument')->name('fleet.drivers.documents.destroy');
        Route::get('/fleet/drivers/{fleetDriver}/documents/{fleetDriverDocument}/download', 'downloadDocument')->name('fleet.drivers.documents.download');
    });

    Route::get('/fleet/options/vehicles', [FleetVehicleController::class, 'optionsForOrder'])
        ->middleware('visibility.area:orders')
        ->name('fleet.options.vehicles');

    Route::get('/fleet/options/drivers', [FleetDriverController::class, 'optionsForOrder'])
        ->middleware('visibility.area:orders')
        ->name('fleet.options.drivers');

    Route::get('/finance', FinanceIndexController::class)->middleware('visibility.area:documents')->name('finance.index');
    Route::get('/documents', [DocumentRegistryController::class, 'index'])->middleware('visibility.area:documents')->name('documents.index');
    Route::post('/documents', [DocumentRegistryController::class, 'store'])->middleware('visibility.area:documents')->name('documents.store');
    Route::patch('/documents/{document}', [DocumentRegistryController::class, 'update'])->middleware('visibility.area:documents')->name('documents.update');
    Route::post('/finance/documents', [FinanceDocumentController::class, 'store'])->middleware('visibility.area:documents')->name('finance.documents.store');
    Route::patch('/finance/documents/{financeDocument}', [FinanceDocumentController::class, 'update'])->middleware('visibility.area:documents')->name('finance.documents.update');
    Route::controller(SettingsKpiController::class)->middleware('visibility.area:finance_salary')->group(function () {
        Route::get('/finance/salary', 'financeSalaryIndex')->name('finance.salary.index');
        Route::post('/finance/salary/periods', 'storeSalaryPeriod')->name('finance.salary.periods.store');
        Route::post('/finance/salary/periods/{salaryPeriod}/recalculate', 'recalculateSalaryPeriod')->name('finance.salary.periods.recalculate');
        Route::post('/finance/salary/periods/{salaryPeriod}/approve', 'approveSalaryPeriod')->name('finance.salary.periods.approve');
        Route::post('/finance/salary/periods/{salaryPeriod}/close', 'closeSalaryPeriod')->name('finance.salary.periods.close');
        Route::post('/finance/salary/periods/{salaryPeriod}/payouts', 'storeSalaryPayout')->name('finance.salary.periods.payouts.store');
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

    Route::get('/reports', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:reports')->name('reports.index');

    Route::get('/modules', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:modules')->name('modules.index');

    Route::get('/settings', SettingsController::class)->middleware('visibility.area:settings')->name('settings.index');

    Route::get('/users', fn () => redirect('/settings/users'));
    Route::get('/roles', fn () => redirect('/settings/roles'));

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Payment Schedule Routes
    Route::prefix('payment-schedules')->name('payment-schedules.')->group(function () {
        Route::post('/{paymentSchedule}/record-payment', [PaymentScheduleController::class, 'recordPayment'])->name('record-payment');
        Route::get('/{paymentSchedule}/partial-payments', [PaymentScheduleController::class, 'getPartialPayments'])->name('partial-payments');
        Route::post('/{paymentSchedule}/cancel', [PaymentScheduleController::class, 'cancel'])->name('cancel');
        Route::post('/{paymentSchedule}/restore', [PaymentScheduleController::class, 'restore'])->name('restore');
    });

    Route::prefix('messenger')->name('messenger.')->group(function () {
        Route::get('/unread-count', [MessengerController::class, 'unreadCount'])->name('unread-count');
        Route::get('/colleagues', [MessengerController::class, 'colleagues'])->name('colleagues');
        Route::get('/document-chips', [MessengerController::class, 'documentChips'])->name('document-chips');
        Route::get('/conversations', [MessengerController::class, 'conversations'])->name('conversations.index');
        Route::post('/conversations/open', [MessengerController::class, 'openDirect'])->name('conversations.open');
        Route::post('/conversations/groups', [MessengerController::class, 'storeGroup'])->name('conversations.groups.store');
        Route::get('/conversations/{conversation}/messages', [MessengerController::class, 'messages'])->name('conversations.messages');
        Route::post('/conversations/{conversation}/messages', [MessengerController::class, 'storeMessage'])->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/read', [MessengerController::class, 'markRead'])->name('conversations.read');
    });

    Route::prefix('cabinet-notifications')->name('cabinet-notifications.')->group(function () {
        Route::get('/summary', [CabinetNotificationController::class, 'summary'])->name('summary');
        Route::get('/', [CabinetNotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [CabinetNotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [CabinetNotificationController::class, 'markAllRead'])->name('read-all');
    });
});

require __DIR__.'/auth.php';
