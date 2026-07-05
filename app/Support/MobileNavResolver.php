<?php

namespace App\Support;

use App\Models\User;
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

        if ($user->isExternal()) {
            return ExternalMobileNavCatalog::forInertiaUser($user);
        }

        $visibleAreas = RoleAccess::userVisibilityAreas($user);
        $isAdmin = RoleAccess::userHasRoleName($user, 'admin');

        $roleDefaultKeys = null;
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            $mergedDefaults = [];
            foreach (RoleAccess::assignedRoles($user) as $role) {
                $rawDefaults = $role->default_mobile_nav_keys;
                if (! is_array($rawDefaults) || $rawDefaults === []) {
                    continue;
                }

                foreach ($rawDefaults as $key) {
                    if (is_string($key) && $key !== '' && ! in_array($key, $mergedDefaults, true)) {
                        $mergedDefaults[] = $key;
                    }
                }
            }

            $roleDefaultKeys = $mergedDefaults !== [] ? $mergedDefaults : null;
        }

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
