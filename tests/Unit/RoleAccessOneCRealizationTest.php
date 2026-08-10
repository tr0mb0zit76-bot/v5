<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class RoleAccessOneCRealizationTest extends TestCase
{
    public function test_clerk_accountant_and_admin_can_create_one_c_realization(): void
    {
        foreach (['clerk' => 'Делопроизводитель', 'accountant' => 'Бухгалтер', 'admin' => 'Админ'] as $name => $display) {
            $role = Role::query()->create([
                'name' => $name,
                'display_name' => $display,
                'permissions' => [],
                'visibility_areas' => ['orders', 'documents'],
            ]);

            $user = User::factory()->make(['role_id' => $role->id]);
            $user->setRelation('role', $role);

            $this->assertTrue(
                RoleAccess::canCreateOneCRealization($user),
                "Expected role {$name} to create 1C realization",
            );
        }
    }

    public function test_manager_and_supervisor_cannot_create_one_c_realization(): void
    {
        foreach (['manager' => 'Менеджер', 'supervisor' => 'Руководитель'] as $name => $display) {
            $role = Role::query()->create([
                'name' => $name,
                'display_name' => $display,
                'permissions' => [],
                'visibility_areas' => ['orders', 'documents'],
            ]);

            $user = User::factory()->make(['role_id' => $role->id]);
            $user->setRelation('role', $role);

            $this->assertFalse(
                RoleAccess::canCreateOneCRealization($user),
                "Expected role {$name} to be denied 1C realization",
            );
        }
    }

    public function test_null_user_cannot_create_one_c_realization(): void
    {
        $this->assertFalse(RoleAccess::canCreateOneCRealization(null));
    }
}
