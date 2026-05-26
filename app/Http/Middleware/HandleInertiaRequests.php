<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\CabinetNotificationBadges;
use App\Support\ContractorTableColumns;
use App\Support\CrmAppearance;
use App\Support\DocumentUploadLimits;
use App\Support\InertiaAppSurface;
use App\Support\LeadTableColumns;
use App\Support\MobileNavPresets;
use App\Support\MobileNavResolver;
use App\Support\OrderTableColumns;
use App\Support\PaymentScheduleTableColumns;
use App\Support\RoleAccess;
use App\Support\ShowcaseUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'document_title_suffix' => Inertia::always(fn () => InertiaAppSurface::fromRequest($request)->documentTitleSuffix()),
            'can_manage_sales_scripts' => Inertia::always(fn () => $request->user() !== null && RoleAccess::canManageSalesScripts($request->user())),
            'flash' => fn () => $request->session()->get('flash'),
            'cabinet_notification_badges' => Inertia::always(fn () => $request->user() === null
                ? ['total' => 0, 'orders' => 0, 'tasks' => 0]
                : CabinetNotificationBadges::unreadFor($request->user())),
            'document_upload_limits' => static fn (): array => DocumentUploadLimits::forSharedInertia(),
            'auth' => Inertia::always(fn () => $this->sharedAuth($request)),
            'showcase_home_url' => Inertia::always(fn () => ShowcaseUrl::home($request)),
            'mobile_nav_presets' => Inertia::always(fn (): array => MobileNavPresets::optionsForUi()),
        ];
    }

    /**
     * @return array{user: ?array<string, mixed>}
     */
    private function sharedAuth(Request $request): array
    {
        $user = $request->user();
        $hasRolesTable = Schema::hasTable('roles');
        $hasVisibilityAreasColumn = $hasRolesTable && Schema::hasColumn('roles', 'visibility_areas');
        $hasVisibilityScopesColumn = $hasRolesTable && Schema::hasColumn('roles', 'visibility_scopes');

        if ($user === null) {
            return ['user' => null];
        }

        return [
            'user' => [
                ...Arr::except($user->toArray(), ['role', 'ui_preferences']),
                'ui_preferences' => CrmAppearance::resolve(
                    is_array($user->ui_preferences) ? $user->ui_preferences : null,
                ),
                'mobile_nav' => MobileNavResolver::forInertiaUser($user),
                'role' => ! $hasRolesTable ? null : (function () use ($user): ?array {
                    $assignedRoles = RoleAccess::assignedRoles($user);

                    if ($assignedRoles->isEmpty()) {
                        return null;
                    }

                    $primaryRole = $assignedRoles->first();
                    $roleNameForDefaults = RoleAccess::userHasRoleName($user, 'admin')
                        ? 'admin'
                        : ($primaryRole->name ?? 'manager');

                    $visibilityAreas = RoleAccess::userVisibilityAreas($user);
                    $visibilityScopes = RoleAccess::mergedVisibilityScopesForUser($user);

                    $displayNames = $assignedRoles
                        ->map(fn (Role $role): string => (string) ($role->display_name ?: $role->name))
                        ->filter()
                        ->values();

                    return [
                        'id' => $primaryRole->id,
                        'name' => $primaryRole->name,
                        'display_name' => $displayNames->count() > 1
                            ? $displayNames->implode(', ')
                            : ($primaryRole->display_name ?? $primaryRole->name),
                        'role_ids' => RoleAccess::userRoleIds($user),
                        'permissions' => RoleAccess::userPermissions($user),
                        'visibility_areas' => $visibilityAreas,
                        'visibility_scopes' => $visibilityScopes !== []
                            ? $visibilityScopes
                            : RoleAccess::defaultVisibilityScopes($roleNameForDefaults),
                        'columns_config' => (function () use ($assignedRoles, $roleNameForDefaults): array {
                            $columnsConfig = [];

                            foreach ($assignedRoles as $role) {
                                $roleConfig = is_array($role->columns_config ?? null)
                                    ? $role->columns_config
                                    : [];

                                foreach ($roleConfig as $table => $preset) {
                                    if (! is_string($table) || ! is_array($preset)) {
                                        continue;
                                    }

                                    if (! isset($columnsConfig[$table])) {
                                        $columnsConfig[$table] = $preset;

                                        continue;
                                    }

                                    foreach ($preset as $columnKey => $columnState) {
                                        if (! is_array($columnState)) {
                                            continue;
                                        }

                                        $existing = $columnsConfig[$table][$columnKey] ?? [];
                                        $columnsConfig[$table][$columnKey] = [
                                            ...$existing,
                                            ...$columnState,
                                            'visible' => (bool) (($existing['visible'] ?? false) || ($columnState['visible'] ?? false)),
                                        ];
                                    }
                                }
                            }

                            $ordersPreset = $columnsConfig['orders'] ?? OrderTableColumns::defaultState($roleNameForDefaults);
                            $columnsConfig['orders'] = OrderTableColumns::mergePresetWithCatalog(
                                is_array($ordersPreset) ? $ordersPreset : [],
                            );

                            $leadsPreset = $columnsConfig['leads'] ?? LeadTableColumns::defaultState($roleNameForDefaults);
                            $columnsConfig['leads'] = LeadTableColumns::mergePresetWithCatalog(
                                is_array($leadsPreset) ? $leadsPreset : [],
                            );

                            $contractorsPreset = $columnsConfig['contractors'] ?? ContractorTableColumns::defaultState($roleNameForDefaults);
                            $columnsConfig['contractors'] = ContractorTableColumns::mergePresetWithCatalog(
                                is_array($contractorsPreset) ? $contractorsPreset : [],
                            );

                            $paymentSchedulePreset = $columnsConfig['payment_schedule'] ?? PaymentScheduleTableColumns::defaultState($roleNameForDefaults);
                            $columnsConfig['payment_schedule'] = PaymentScheduleTableColumns::mergePresetWithCatalog(
                                is_array($paymentSchedulePreset) ? $paymentSchedulePreset : [],
                            );

                            return $columnsConfig;
                        })(),
                    ];
                })(),
            ],
        ];
    }
}
