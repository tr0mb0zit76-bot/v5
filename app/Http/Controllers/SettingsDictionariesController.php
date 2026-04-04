<?php

namespace App\Http\Controllers;

use App\Models\ContractorActivityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsDictionariesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

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
            ],
        ]);
    }

    public function storeActivityType(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

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
        abort_unless($request->user()?->isAdmin(), 403);

        $contractorActivityType->delete();

        return to_route('settings.dictionaries.index');
    }
}
