<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoleTablePresetRequest;
use App\Models\Role;
use App\Support\ContractorTableColumns;
use App\Support\LeadTableColumns;
use App\Support\OrderTableColumns;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsTableManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        return Inertia::render('Settings/Tables', [
            'roles' => Role::query()
                ->orderBy('display_name')
                ->orderBy('name')
                ->get()
                ->map(function (Role $role): array {
                    $columnsConfig = is_array($role->columns_config) ? $role->columns_config : [];

                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                        'columns_config' => [
                            'orders' => $columnsConfig['orders'] ?? OrderTableColumns::defaultState($role->name),
                            'leads' => $columnsConfig['leads'] ?? LeadTableColumns::defaultState($role->name),
                            'contractors' => $columnsConfig['contractors'] ?? ContractorTableColumns::defaultState($role->name),
                        ],
                    ];
                })
                ->values(),
            'orderColumns' => OrderTableColumns::options(),
            'leadColumns' => LeadTableColumns::options(),
            'contractorColumns' => ContractorTableColumns::options(),
        ]);
    }

    public function update(UpdateRoleTablePresetRequest $request, Role $role): RedirectResponse
    {
        $columnsConfig = is_array($role->columns_config) ? $role->columns_config : [];
        $columnsConfig[$request->validated('table')] = $request->validated('columns');

        $role->update([
            'columns_config' => $columnsConfig,
        ]);

        return to_route('settings.tables.index');
    }
}
