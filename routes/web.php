<?php

use App\Http\Controllers\ContractorController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Orders\OrderIndexController;
use App\Http\Controllers\Orders\OrderWizardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsDictionariesController;
use App\Http\Controllers\SettingsKpiController;
use App\Http\Controllers\SettingsTableManagementController;
use App\Http\Controllers\SettingsTemplateController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return app(PublicSiteController::class)->home();
});

Route::controller(PublicSiteController::class)->group(function () {
    Route::get('/about', 'about')->name('public.about');
    Route::get('/services', 'services')->name('public.services');
    Route::get('/cases', 'cases')->name('public.cases');
    Route::get('/contacts', 'contacts')->name('public.contacts');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:dashboard')->name('dashboard');

    Route::controller(LeadController::class)->middleware('visibility.area:leads')->group(function () {
        Route::get('/leads', 'index')->name('leads.index');
        Route::get('/leads/create', 'create')->name('leads.create');
        Route::post('/leads', 'store')->name('leads.store');
        Route::get('/leads/{lead}', 'show')->name('leads.show');
        Route::patch('/leads/{lead}', 'update')->name('leads.update');
        Route::delete('/leads/{lead}', 'destroy')->name('leads.destroy');
        Route::post('/leads/{lead}/proposal', 'prepareProposal')->name('leads.proposal');
        Route::post('/leads/{lead}/convert', 'convert')->name('leads.convert');
    });

    Route::get('/orders', OrderIndexController::class)->middleware('visibility.area:orders')->name('orders.index');
    Route::controller(OrderWizardController::class)->middleware('visibility.area:orders')->group(function () {
        Route::get('/orders/create', 'create')->name('orders.create');
        Route::post('/orders', 'store')->name('orders.store');
        Route::get('/orders/{order}/edit', 'edit')->name('orders.edit');
        Route::patch('/orders/{order}', 'update')->name('orders.update');
        Route::get('/orders/{order}/templates/{printFormTemplate}/draft', 'generateDocumentDraft')->name('orders.templates.generate-draft');
        Route::patch('/orders/{order}/inline', 'inlineUpdate')->name('orders.inline-update');
        Route::delete('/orders/{order}', 'destroy')->withTrashed()->name('orders.destroy');
        Route::get('/orders-suggest/address', 'suggestAddress')->name('orders.suggest-address');
        Route::post('/orders/contractors', 'storeContractor')->name('orders.contractors.store');
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
        Route::get('/contractors/{contractor}/edit', 'edit')->name('contractors.edit');
        Route::patch('/contractors/{contractor}', 'update')->name('contractors.update');
        Route::delete('/contractors/{contractor}', 'destroy')->name('contractors.destroy');
        Route::post('/contractors/activity-types', 'storeActivityType')->name('contractors.activity-types.store');
        Route::get('/contractors-suggest/party', 'suggestParty')->name('contractors.suggest-party');
        Route::get('/contractors-suggest/address', 'suggestAddress')->name('contractors.suggest-address');
    });

    Route::get('/drivers', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:drivers')->name('drivers.index');

    Route::get('/documents', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:documents')->name('documents.index');

    Route::get('/activities', function () {
        return Inertia::render('Dashboard');
    })->middleware('visibility.area:activities')->name('activities.index');

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
});

require __DIR__.'/auth.php';
