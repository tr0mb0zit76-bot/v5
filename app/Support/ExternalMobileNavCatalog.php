<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Нижняя навигация Traklo для внешних пользователей контрагента.
 */
final class ExternalMobileNavCatalog
{
    public const ORDER = ['counterparty_orders', 'counterparty_documents', 'chats', 'counterparty_portal'];

    /**
     * @return list<string>
     */
    public static function candidateKeys(User $user): array
    {
        $visibleAreas = RoleAccess::userVisibilityAreas($user);
        $areaSet = array_flip($visibleAreas);
        $party = $user->externalParty();

        $out = [];

        foreach (self::ORDER as $key) {
            if ($key === 'chats') {
                $out[] = $key;

                continue;
            }

            if ($key === 'counterparty_portal' && $party !== ExternalParty::Carrier) {
                continue;
            }

            $areaKey = $key;
            if (isset($areaSet[$areaKey])) {
                $out[] = $key;
            }
        }

        return $out !== [] ? $out : ['chats'];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'counterparty_orders' => 'Заказы',
            'counterparty_documents' => 'Документы',
            'chats' => 'Чаты',
            'counterparty_portal' => 'Рейс',
        ];
    }

    /**
     * @return array{resolved_keys: list<string>, candidate_keys: list<string>, labels: array<string, string>}|null
     */
    public static function forInertiaUser(User $user): ?array
    {
        if (! Schema::hasColumn('users', 'is_external') || ! $user->isExternal()) {
            return null;
        }

        $candidates = self::candidateKeys($user);
        $roleDefaultKeys = null;

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            foreach (RoleAccess::assignedRoles($user) as $role) {
                $rawDefaults = $role->default_mobile_nav_keys;
                if (is_array($rawDefaults) && $rawDefaults !== []) {
                    $roleDefaultKeys = $rawDefaults;

                    break;
                }
            }
        }

        $userKeys = is_array($user->mobile_nav_keys) && $user->mobile_nav_keys !== [] ? $user->mobile_nav_keys : null;
        $resolved = MobileNavPreference::resolve($candidates, $userKeys, $roleDefaultKeys);

        return [
            'resolved_keys' => $resolved,
            'candidate_keys' => $candidates,
            'labels' => self::labels(),
        ];
    }
}
