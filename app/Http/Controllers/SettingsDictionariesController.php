<?php

namespace App\Http\Controllers;

use App\Models\ContractorActivityType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\VatRate;
use App\Support\RoleAccess;
use App\Support\VatRateCode;
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
                [
                    'key' => 'vat-rates',
                    'title' => 'Ставки НДС',
                    'description' => 'Варианты «С НДС …%» для формы оплаты в заказах, контрагентах и таблице заказов.',
                    'items' => Schema::hasTable('vat_rates')
                        ? VatRate::query()
                            ->orderBy('sort_order')
                            ->orderByDesc('rate_percent')
                            ->get(['id', 'code', 'label', 'rate_percent'])
                            ->map(fn (VatRate $item): array => [
                                'id' => $item->id,
                                'code' => $item->code,
                                'label' => $item->label,
                                'rate_percent' => (float) $item->rate_percent,
                            ])
                            ->all()
                        : [],
                ],
                [
                    'key' => 'departments',
                    'title' => 'Подразделения',
                    'description' => 'Организационные подразделения для карточек пользователей и маршрутизации согласований.',
                    'items' => Schema::hasTable('departments')
                        ? Department::query()
                            ->withCount('users')
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->get(['id', 'name', 'sort_order', 'is_active'])
                            ->map(fn (Department $item): array => [
                                'id' => $item->id,
                                'name' => $item->name,
                                'sort_order' => (int) $item->sort_order,
                                'is_active' => (bool) $item->is_active,
                                'users_count' => (int) $item->users_count,
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

    public function storeVatRate(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('vat_rates'), 404, 'Справочник ставок НДС недоступен.');

        $validated = $request->validate([
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100', Rule::unique('vat_rates', 'rate_percent')],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $rate = round((float) $validated['rate_percent'], 4);
        $code = VatRateCode::fromRate($rate);

        abort_if(
            VatRate::query()->where('code', $code)->exists(),
            422,
            'Для этой ставки уже существует код. Измените ставку или удалите дубликат.'
        );

        $label = isset($validated['label']) && trim((string) $validated['label']) !== ''
            ? trim((string) $validated['label'])
            : 'С НДС '.rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.').'%';

        $nextOrder = (int) (VatRate::query()->max('sort_order') ?? 0) + 10;

        VatRate::query()->create([
            'code' => $code,
            'label' => $label,
            'rate_percent' => $rate,
            'sort_order' => $nextOrder,
        ]);

        return to_route('settings.dictionaries.index');
    }

    public function destroyVatRate(Request $request, VatRate $vatRate): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('vat_rates'), 404, 'Справочник ставок НДС недоступен.');

        $vatRate->delete();

        return to_route('settings.dictionaries.index');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('departments'), 404, 'Справочник подразделений недоступен.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')],
        ]);

        $nextOrder = (int) (Department::query()->max('sort_order') ?? 0) + 10;

        Department::query()->create([
            'name' => trim($validated['name']),
            'sort_order' => $nextOrder,
            'is_active' => true,
        ]);

        return to_route('settings.dictionaries.index');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('departments'), 404, 'Справочник подразделений недоступен.');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department->update([
            'name' => trim($validated['name']),
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $department->is_active,
        ]);

        return to_route('settings.dictionaries.index');
    }

    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);
        abort_unless(Schema::hasTable('departments'), 404, 'Справочник подразделений недоступен.');

        if ($department->users()->exists()) {
            return to_route('settings.dictionaries.index')
                ->withErrors([
                    'department' => 'Нельзя удалить подразделение: к нему привязаны пользователи. Отключите его или переназначьте сотрудников.',
                ]);
        }

        $department->delete();

        return to_route('settings.dictionaries.index');
    }
}
