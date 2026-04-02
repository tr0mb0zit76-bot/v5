<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    'role' => $user->role_id === null ? null : (function () use ($user) {
                        $columns = ['id', 'name', 'display_name', 'permissions', 'visibility_areas', 'columns_config'];

                        if (Schema::hasColumn('roles', 'visibility_scopes')) {
                            $columns[] = 'visibility_scopes';
                        }

                        $role = DB::table('roles')
                        ->where('id', $user->role_id)
                        ->select($columns)
                        ->first();

                        if ($role === null) {
                            return null;
                        }

                        $rawPermissions = property_exists($role, 'permissions') ? $role->permissions : null;
                        $rawVisibilityAreas = property_exists($role, 'visibility_areas') ? $role->visibility_areas : null;
                        $rawVisibilityScopes = property_exists($role, 'visibility_scopes') ? $role->visibility_scopes : null;
                        $rawColumnsConfig = property_exists($role, 'columns_config') ? $role->columns_config : null;

                        $permissions = is_string($rawPermissions)
                            ? json_decode($rawPermissions, true)
                            : $rawPermissions;

                        $visibilityAreas = is_string($rawVisibilityAreas)
                            ? json_decode($rawVisibilityAreas, true)
                            : $rawVisibilityAreas;

                        $columnsConfig = is_string($rawColumnsConfig)
                            ? json_decode($rawColumnsConfig, true)
                            : $rawColumnsConfig;
                        $visibilityScopes = is_string($rawVisibilityScopes)
                            ? json_decode($rawVisibilityScopes, true)
                            : $rawVisibilityScopes;

                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'display_name' => $role->display_name,
                            'permissions' => is_array($permissions) ? $permissions : [],
                            'visibility_areas' => is_array($visibilityAreas)
                                ? $visibilityAreas
                                : RoleAccess::defaultVisibilityAreas($role->name),
                            'visibility_scopes' => is_array($visibilityScopes)
                                ? $visibilityScopes
                                : RoleAccess::defaultVisibilityScopes($role->name),
                            'columns_config' => is_array($columnsConfig) ? $columnsConfig : [],
                        ];
                    })(),
                ],
            ],
        ];
    }
}
