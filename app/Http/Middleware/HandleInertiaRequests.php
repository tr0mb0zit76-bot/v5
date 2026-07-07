<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\Documents\OcrServiceClient;
use App\Services\GridViewService;
use App\Support\AiAgentCatalog;
use App\Support\CabinetNotificationBadges;
use App\Support\CommandBarHistoryLimits;
use App\Support\ContractorTableColumns;
use App\Support\CrmAppearance;
use App\Support\CrmFeatureCatalog;
use App\Support\DocumentUploadLimits;
use App\Support\InertiaAppSurface;
use App\Support\LeadTableColumns;
use App\Support\MobileNavPresets;
use App\Support\MobileNavResolver;
use App\Support\OrderTableColumns;
use App\Support\PaymentScheduleTableColumns;
use App\Support\RoleAccess;
use App\Support\ShowcaseUrl;
use App\Support\SidebarMenuFavoritesResolver;
use App\Support\TableColumnsPreset;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
     * Inertia page.url: при APP_URL=https или X-Forwarded-Proto не оставляем http в fullUrl за прокси.
     */
    public function urlResolver(): Closure
    {
        return function (Request $request): string {
            $fullUrl = $this->resolveRequestFullUrl($request);
            $schemeAndHttpHost = $this->schemeAndHttpHostFromFullUrl($fullUrl, $request);
            $url = Str::start(Str::after($fullUrl, $schemeAndHttpHost), '/');
            $rawUri = Str::before($request->getRequestUri(), '?');

            if (Str::endsWith($rawUri, '/')) {
                $urlWithoutQueryWithTrailingSlash = Str::finish(Str::before($url, '?'), '/');

                return str_contains($url, '?')
                    ? $urlWithoutQueryWithTrailingSlash.'?'.Str::after($url, '?')
                    : $urlWithoutQueryWithTrailingSlash;
            }

            return $url;
        };
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
            'can_export_grid' => Inertia::always(fn () => $request->user() !== null && RoleAccess::canExportGrid($request->user())),
            'flash' => fn () => $request->session()->get('flash'),
            'cabinet_notification_badges' => Inertia::always(fn () => $request->user() === null
                ? ['total' => 0, 'orders' => 0, 'tasks' => 0]
                : CabinetNotificationBadges::unreadFor($request->user())),
            'document_upload_limits' => Inertia::always(static fn (): array => DocumentUploadLimits::forSharedInertia()),
            'document_optimize' => Inertia::always(static fn (): array => [
                'enabled' => app(OcrServiceClient::class)->isOptimizeEnabled(),
            ]),
            'auth' => Inertia::always(fn () => $this->sharedAuth($request)),
            'showcase_home_url' => Inertia::always(fn () => ShowcaseUrl::home($request)),
            'mobile_nav_presets' => Inertia::always(fn (): array => MobileNavPresets::optionsForUi()),
            'ai_agents' => Inertia::always(fn () => AiAgentCatalog::optionsForUser($request->user())),
            'ai_agent_default_slug' => Inertia::always(fn (): string => AiAgentCatalog::defaultSlug()),
            'ai_command_bar_history' => Inertia::always(fn (): array => CommandBarHistoryLimits::profileForUser($request->user())),
            'crm_features' => Inertia::always(fn (): array => CrmFeatureCatalog::snapshot($request->user())),
            'mobile_push_enabled' => Inertia::always(static fn (): bool => (bool) config('fcm.enabled')),
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
                'sidebar_favorites' => SidebarMenuFavoritesResolver::forInertiaUser($user),
                'pinned_grid_views' => app(GridViewService::class)->pinnedForSidebar($user),
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
                        'is_admin' => RoleAccess::userHasRoleName($user, 'admin'),
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
                            $presetsByTable = [];

                            foreach ($assignedRoles as $role) {
                                $roleConfig = is_array($role->columns_config ?? null)
                                    ? $role->columns_config
                                    : [];

                                foreach ($roleConfig as $table => $preset) {
                                    if (! is_string($table) || ! is_array($preset) || $preset === []) {
                                        continue;
                                    }

                                    $presetsByTable[$table][] = $preset;
                                }
                            }

                            $resolveMergedPreset = function (string $table, array $presets, callable $defaultState, callable $mergeWithCatalog): array {
                                if ($presets === []) {
                                    return $mergeWithCatalog($defaultState());
                                }

                                return $mergeWithCatalog(TableColumnsPreset::unionPresetsByColId($presets));
                            };

                            return [
                                'orders' => $resolveMergedPreset(
                                    'orders',
                                    $presetsByTable['orders'] ?? [],
                                    fn (): array => OrderTableColumns::defaultState($roleNameForDefaults),
                                    fn (array $preset): array => OrderTableColumns::mergePresetWithCatalog($preset),
                                ),
                                'leads' => $resolveMergedPreset(
                                    'leads',
                                    $presetsByTable['leads'] ?? [],
                                    fn (): array => LeadTableColumns::defaultState($roleNameForDefaults),
                                    fn (array $preset): array => LeadTableColumns::mergePresetWithCatalog($preset),
                                ),
                                'contractors' => $resolveMergedPreset(
                                    'contractors',
                                    $presetsByTable['contractors'] ?? [],
                                    fn (): array => ContractorTableColumns::defaultState($roleNameForDefaults),
                                    fn (array $preset): array => ContractorTableColumns::mergePresetWithCatalog($preset),
                                ),
                                'payment_schedule' => $resolveMergedPreset(
                                    'payment_schedule',
                                    $presetsByTable['payment_schedule'] ?? [],
                                    fn (): array => PaymentScheduleTableColumns::defaultState($roleNameForDefaults),
                                    fn (array $preset): array => PaymentScheduleTableColumns::mergePresetWithCatalog($preset),
                                ),
                            ];
                        })(),
                    ];
                })(),
            ],
        ];
    }

    private function resolveRequestFullUrl(Request $request): string
    {
        $url = $request->fullUrl();

        if ($this->shouldServeUrlsAsHttps($request) && str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }

    private function schemeAndHttpHostFromFullUrl(string $fullUrl, Request $request): string
    {
        $parts = parse_url($fullUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $request->getSchemeAndHttpHost();
        }

        $schemeAndHost = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $defaultPort = $parts['scheme'] === 'https' ? 443 : 80;

            if ((int) $parts['port'] !== $defaultPort) {
                $schemeAndHost .= ':'.$parts['port'];
            }
        }

        return $schemeAndHost;
    }

    private function shouldServeUrlsAsHttps(Request $request): bool
    {
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $forwarded = $request->header('X-Forwarded-Proto');

        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }

        if ($request->isSecure()) {
            return true;
        }

        return false;
    }
}
