<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\User;

/**
 * Владелец карточки (или admin/supervisor) управляет портретом и enrichment.
 */
final class ContractorPortraitAuthorization
{
    public static function canManage(?User $user, Contractor $contractor): bool
    {
        if ($user === null) {
            return false;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return (int) $user->id === (int) ($contractor->owner_id ?? 0);
    }

    public static function authorizeManage(?User $user, Contractor $contractor): void
    {
        abort_unless(self::canManage($user, $contractor), 403, 'Портрет может менять только владелец карточки.');
    }
}
