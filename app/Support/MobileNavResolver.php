<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MobileNavResolver
{
    /**
     * @return array{resolved_keys: list<string>, candidate_keys: list<string>, labels: array<string, string>}|null
     */
    public static function forInertiaUser(?User $user): ?array
    {
        if ($user === null || ! Schema::hasColumn('users', 'mobile_nav_keys')) {
            return null;
        }

        $roleName = null;
        $visibleAreas = [];
        $roleDefaultKeys = null;

        if ($user->role_id !== null && Schema::hasTable('roles')) {
            $columns = ['name'];

            if (Schema::hasColumn('roles', 'visibility_areas')) {
                $columns[] = 'visibility_areas';
            }

            if (Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
                $columns[] = 'default_mobile_nav_keys';
            }

            $role = DB::table('roles')
                ->where('id', $user->role_id)
                ->select($columns)
                ->first();

            if ($role !== null) {
                $roleName = $role->name;
                $visibleAreas = RoleAccess::effectiveVisibilityAreasFromRolePayload(
                    $roleName,
                    property_exists($role, 'visibility_areas') ? $role->visibility_areas : null,
                );

                if (Schema::hasColumn('roles', 'default_mobile_nav_keys') && property_exists($role, 'default_mobile_nav_keys')) {
                    $rawDefaults = $role->default_mobile_nav_keys;
                    if (is_string($rawDefaults)) {
                        $rawDefaults = json_decode($rawDefaults, true);
                    }
                    $roleDefaultKeys = is_array($rawDefaults) && $rawDefaults !== [] ? $rawDefaults : null;
                }
            }
        }

        $isAdmin = $roleName === 'admin';
        $candidates = MobileNavCatalog::candidateKeys($isAdmin, $visibleAreas);

        $userKeys = $user->mobile_nav_keys;
        if (! is_array($userKeys) || $userKeys === []) {
            $userKeys = null;
        }

        $resolved = MobileNavPreference::resolve($candidates, $userKeys, $roleDefaultKeys);

        return [
            'resolved_keys' => $resolved,
            'candidate_keys' => $candidates,
            'labels' => MobileNavCatalog::labels(),
        ];
    }

    /**
     * Оставляет только разрешённые ключи, сохраняя порядок выбора пользователя.
     *
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public static function sanitizeUserSelection(User $user, array $requestedKeys): array
    {
        $nav = self::forInertiaUser($user);
        if ($nav === null) {
            return [];
        }

        $allowed = array_flip($nav['candidate_keys']);
        $picked = [];

        foreach ($requestedKeys as $key) {
            if (! is_string($key) || $key === '' || ! isset($allowed[$key])) {
                continue;
            }

            if (in_array($key, $picked, true)) {
                continue;
            }

            $picked[] = $key;

            if (count($picked) >= MobileNavCatalog::maxSelectable()) {
                break;
            }
        }

        return $picked;
    }
}
