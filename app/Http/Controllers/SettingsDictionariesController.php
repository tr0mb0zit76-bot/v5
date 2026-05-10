<?php

namespace App\Http\Controllers;

use App\Models\ContractorActivityType;
use App\Models\Currency;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsDictionariesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        return Inertia::render('Settings/Dictionaries', [
            'dictionaries' => [
                [
                    'key' => 'contractor-activity-types',
                    'title' => 'Виды деятельности',
                    'description' => 'Глобальный справочник для карточек контрагентов, фильтров и отчётов.',
                    'items' => ContractorActivityType::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (ContractorActivityType $item): array => [
                            'id' => $item->id,
                            'name' => $item->name,
                        ])
                        ->all(),
                ],
                [
                    'key' => 'currencies',
                    'title' => 'Валюты',
                    'description' => 'Коды ISO и подписи для лимитов контрагентов, заказов и лидов.',
                    'items' => Schema::hasTable('currencies')
                        ? Currency::query()
                            ->orderBy('sort_order')
                            ->orderBy('code')
                            ->get(['id', 'code', 'name'])
                            ->map(fn (Currency $item): array => [
                                'id' => $item->id,
                                'code' => $item->code,
                                'name' => $item->name,
                            ])
                            ->all()
                        : [],
                ],
            ],
        ]);
    }

    public function storeActivityType(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('contractor_activity_types', 'name')],
        ]);

        ContractorActivityType::query()->create([
            'name' => trim($validated['name']),
        ]);

        return to_route('settings.dictionaries.index');
    }

    public function destroyActivityType(Request $request, ContractorActivityType $contractorActivityType): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $contractorActivityType->delete();

        return to_route('settings.dictionaries.index');
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('currencies'), 404, 'Справочник валют недоступен.');

        $request->merge([
            'code' => strtoupper(trim($request->string('code')->toString())),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) (Currency::query()->max('sort_order') ?? 0) + 10;

        Currency::query()->create([
            'code' => $validated['code'],
            'name' => trim($validated['name']),
            'sort_order' => $nextOrder,
        ]);

        return to_route('settings.dictionaries.index');
    }

    public function destroyCurrency(Request $request, Currency $currency): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('currencies'), 404, 'Справочник валют недоступен.');

        $currency->delete();

        return to_route('settings.dictionaries.index');
    }
}
