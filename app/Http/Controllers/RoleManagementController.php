<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Support\MobileNavCatalog;
use App\Support\MobileNavPresets;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class RoleManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->withCount('users')
                ->orderBy('display_name')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'permissions' => $role->permissions ?? [],
                    'visibility_areas' => RoleAccess::effectiveVisibilityAreasFromRolePayload($role->name, $role->visibility_areas ?? null),
                    'visibility_scopes' => Schema::hasColumn('roles', 'visibility_scopes')
                        ? ($role->visibility_scopes ?? RoleAccess::defaultVisibilityScopes($role->name))
                        : RoleAccess::defaultVisibilityScopes($role->name),
                    'default_has_signing_authority' => Schema::hasColumn('roles', 'has_signing_authority')
                        ? (bool) $role->has_signing_authority
                        : false,
                    'default_mobile_nav_keys' => Schema::hasColumn('roles', 'default_mobile_nav_keys')
                        ? ($role->default_mobile_nav_keys ?? null)
                        : null,
                    'users_count' => $role->users_count,
                ])
                ->values(),
            'permissionOptions' => RoleAccess::permissionOptions(),
            'visibilityAreaOptions' => RoleAccess::visibilityAreaOptions(),
            'visibilityScopeOptions' => RoleAccess::visibilityScopeOptions(),
            'mobileNavCatalog' => MobileNavCatalog::optionsForUi(),
            'mobileNavPresets' => MobileNavPresets::optionsForUi(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::query()->create($this->rolePersistAttributesFromFormRequest($request));

        return to_route('settings.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($this->rolePersistAttributesFromFormRequest($request));

        return to_route('settings.roles.index');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($role->users()->exists(), 422, 'Нельзя удалить роль, которая назначена пользователям.');
        abort_if($role->name === 'admin', 422, 'Нельзя удалить системную роль администратора.');

        $role->delete();

        return to_route('settings.roles.index');
    }

    /**
     * @param  array<string, array{mode?: string}>  $visibilityScopes
     * @param  list<string>  $visibilityAreas
     * @return array<string, string>
     */
    private function normalizeVisibilityScopes(array $visibilityScopes, array $visibilityAreas): array
    {
        return collect($visibilityAreas)
            ->mapWithKeys(function (string $area) use ($visibilityScopes): array {
                $mode = Arr::get($visibilityScopes, $area.'.mode');

                if (! in_array($mode, ['own', 'all'], true)) {
                    $mode = 'own';
                }

                return [$area => $mode];
            })
            ->all();
    }

    /**
     * Явное сопоставление полей после валидации (без spread по validated()), чтобы области видимости и права точно попадали в БД.
     *
     * @return array<string, mixed>
     */
    private function rolePersistAttributesFromFormRequest(FormRequest $request): array
    {
        $validated = $request->validated();

        $visibilityAreas = ($validated['name'] ?? null) === 'admin'
            ? RoleAccess::visibilityAreaKeys()
            : array_values(array_unique($validated['visibility_areas'] ?? []));

        $attributes = [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'permissions' => is_array($validated['permissions'] ?? null) ? $validated['permissions'] : [],
            'visibility_areas' => $visibilityAreas,
        ];

        if (Schema::hasColumn('roles', 'visibility_scopes')) {
            $attributes['visibility_scopes'] = $this->normalizeVisibilityScopes(
                is_array($validated['visibility_scopes'] ?? null) ? $validated['visibility_scopes'] : [],
                $visibilityAreas,
            );
        }

        if (Schema::hasColumn('roles', 'has_signing_authority')) {
            $attributes['has_signing_authority'] = (bool) ($validated['has_signing_authority'] ?? false);
        }

        if (Schema::hasColumn('roles', 'default_mobile_nav_keys') && array_key_exists('default_mobile_nav_keys', $validated)) {
            $keys = $validated['default_mobile_nav_keys'];
            $attributes['default_mobile_nav_keys'] = is_array($keys) && $keys !== [] ? array_values($keys) : null;
        }

        return $attributes;
    }
}
