<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\CabinetNotificationBadges;
use App\Support\ContractorTableColumns;
use App\Support\DocumentUploadLimits;
use App\Support\InertiaAppSurface;
use App\Support\LeadTableColumns;
use App\Support\MobileNavResolver;
use App\Support\OrderTableColumns;
use App\Support\PaymentScheduleTableColumns;
use App\Support\RoleAccess;
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
                ...Arr::except($user->toArray(), ['role']),
                'mobile_nav' => MobileNavResolver::forInertiaUser($user),
                'role' => $user->role_id === null || ! $hasRolesTable ? null : (function () use ($user, $hasVisibilityAreasColumn, $hasVisibilityScopesColumn): ?array {
                    $roleModel = Role::query()->find($user->role_id);

                    if ($roleModel === null) {
                        return null;
                    }

                    $rawVisibilityAreas = $hasVisibilityAreasColumn ? ($roleModel->visibility_areas ?? null) : null;
                    $rawVisibilityScopes = $hasVisibilityScopesColumn ? ($roleModel->visibility_scopes ?? null) : null;

                    $visibilityAreas = RoleAccess::effectiveVisibilityAreasFromRolePayload(
                        $roleModel->name,
                        $rawVisibilityAreas,
                    );

                    $visibilityScopes = RoleAccess::coerceVisibilityScopes($rawVisibilityScopes);

                    return [
                        'id' => $roleModel->id,
                        'name' => $roleModel->name,
                        'display_name' => $roleModel->display_name,
                        'permissions' => is_array($roleModel->permissions ?? null) ? $roleModel->permissions : [],
                        'visibility_areas' => $visibilityAreas,
                        'visibility_scopes' => is_array($visibilityScopes)
                            ? $visibilityScopes
                            : RoleAccess::defaultVisibilityScopes($roleModel->name),
                        'columns_config' => (function () use ($roleModel): array {
                            $columnsConfig = is_array($roleModel->columns_config ?? null)
                                ? $roleModel->columns_config
                                : [];

                            $ordersPreset = $columnsConfig['orders'] ?? OrderTableColumns::defaultState($roleModel->name);
                            $columnsConfig['orders'] = OrderTableColumns::mergePresetWithCatalog(
                                is_array($ordersPreset) ? $ordersPreset : [],
                            );

                            $leadsPreset = $columnsConfig['leads'] ?? LeadTableColumns::defaultState($roleModel->name);
                            $columnsConfig['leads'] = LeadTableColumns::mergePresetWithCatalog(
                                is_array($leadsPreset) ? $leadsPreset : [],
                            );

                            $contractorsPreset = $columnsConfig['contractors'] ?? ContractorTableColumns::defaultState($roleModel->name);
                            $columnsConfig['contractors'] = ContractorTableColumns::mergePresetWithCatalog(
                                is_array($contractorsPreset) ? $contractorsPreset : [],
                            );

                            $paymentSchedulePreset = $columnsConfig['payment_schedule'] ?? PaymentScheduleTableColumns::defaultState($roleModel->name);
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
