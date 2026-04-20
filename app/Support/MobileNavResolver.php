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
                $rawAreas = property_exists($role, 'visibility_areas') ? $role->visibility_areas : null;
                if (is_string($rawAreas)) {
                    $rawAreas = json_decode($rawAreas, true);
                }
                $visibleAreas = is_array($rawAreas)
                    ? $rawAreas
                    : RoleAccess::defaultVisibilityAreas($roleName);

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
}
