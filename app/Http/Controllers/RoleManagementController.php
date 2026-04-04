<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Support\RoleAccess;
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
                    'visibility_areas' => $role->visibility_areas ?? RoleAccess::defaultVisibilityAreas($role->name),
                    'visibility_scopes' => Schema::hasColumn('roles', 'visibility_scopes')
                        ? ($role->visibility_scopes ?? RoleAccess::defaultVisibilityScopes($role->name))
                        : RoleAccess::defaultVisibilityScopes($role->name),
                    'default_has_signing_authority' => Schema::hasColumn('roles', 'has_signing_authority')
                        ? (bool) $role->has_signing_authority
                        : false,
                    'users_count' => $role->users_count,
                ])
                ->values(),
            'permissionOptions' => RoleAccess::permissionOptions(),
            'visibilityAreaOptions' => RoleAccess::visibilityAreaOptions(),
            'visibilityScopeOptions' => RoleAccess::visibilityScopeOptions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $attributes = [
            ...Arr::except($request->validated(), ['visibility_scopes']),
            'permissions' => $request->validated('permissions', []),
        ];

        if (Schema::hasColumn('roles', 'visibility_scopes')) {
            $attributes['visibility_scopes'] = $this->normalizeVisibilityScopes(
                $request->validated('visibility_scopes', []),
                $request->validated('visibility_areas', [])
            );
        }

        if (Schema::hasColumn('roles', 'has_signing_authority')) {
            $attributes['has_signing_authority'] = (bool) $request->validated('has_signing_authority', false);
        }

        Role::query()->create($attributes);

        return to_route('settings.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $attributes = [
            ...Arr::except($request->validated(), ['visibility_scopes']),
            'permissions' => $request->validated('permissions', []),
        ];

        if (Schema::hasColumn('roles', 'visibility_scopes')) {
            $attributes['visibility_scopes'] = $this->normalizeVisibilityScopes(
                $request->validated('visibility_scopes', []),
                $request->validated('visibility_areas', [])
            );
        }

        if (Schema::hasColumn('roles', 'has_signing_authority')) {
            $attributes['has_signing_authority'] = (bool) $request->validated('has_signing_authority', false);
        }

        $role->update($attributes);

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
}
