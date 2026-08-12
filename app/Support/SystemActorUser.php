<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Технический пользователь «Система» для cron/OData (imported_by, allocated_by).
 */
final class SystemActorUser
{
    public static function resolve(): User
    {
        $configUserId = config('one_c.system_actor.user_id');
        if (is_int($configUserId) && $configUserId > 0) {
            $byId = User::query()->find($configUserId);
            if ($byId !== null) {
                return self::ensureCapabilities($byId);
            }
        }

        $email = trim((string) config('one_c.system_actor.email', 'system@crm.local'));
        if ($email === '') {
            $email = 'system@crm.local';
        }

        $name = trim((string) config('one_c.system_actor.name', 'Система'));
        if ($name === '') {
            $name = 'Система';
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $attrs = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
                'can_management_accounting' => true,
            ];

            if (Schema::hasColumn('users', 'email_verified_at')) {
                $attrs['email_verified_at'] = now();
            }

            $user = User::query()->create($attrs);
        }

        return self::ensureCapabilities($user, $name);
    }

    private static function ensureCapabilities(User $user, ?string $preferredName = null): User
    {
        $dirty = false;

        if ($preferredName !== null && trim((string) $user->name) !== $preferredName) {
            $user->name = $preferredName;
            $dirty = true;
        }

        if (Schema::hasColumn('users', 'can_management_accounting') && ! $user->can_management_accounting) {
            $user->can_management_accounting = true;
            $dirty = true;
        }

        if (Schema::hasColumn('users', 'is_active') && ! $user->is_active) {
            $user->is_active = true;
            $dirty = true;
        }

        if (Schema::hasColumn('users', 'role_id') && ! $user->isAdmin()) {
            $adminRoleId = Role::query()
                ->where('name', 'admin')
                ->value('id');

            if ($adminRoleId !== null && (int) $user->role_id !== (int) $adminRoleId) {
                $user->role_id = (int) $adminRoleId;
                $dirty = true;
            }
        }

        if ($dirty) {
            $user->save();
        }

        return $user->fresh() ?? $user;
    }
}
