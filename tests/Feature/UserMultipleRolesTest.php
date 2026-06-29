<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class UserMultipleRolesTest extends TestCase
{
    public function test_user_with_manager_and_dispatcher_roles_merges_visibility_areas(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager_multi',
            'display_name' => 'Менеджер',
            'permissions' => ['view_orders'],
            'visibility_areas' => ['orders', 'contractors', 'leads'],
            'visibility_scopes' => [
                'contractors' => 'own',
                'orders' => 'own',
            ],
        ]);

        $dispatcherRole = Role::query()->create([
            'name' => 'dispatcher_multi',
            'display_name' => 'Диспетчер',
            'permissions' => ['assign_drivers'],
            'visibility_areas' => ['drivers', 'orders'],
            'visibility_scopes' => [
                'orders' => 'all',
            ],
        ]);

        $user = User::factory()->create(['role_id' => $managerRole->id]);
        RoleAccess::syncUserRoles($user, [$managerRole->id, $dispatcherRole->id]);

        $areas = RoleAccess::userVisibilityAreas($user->fresh());

        $this->assertContains('orders', $areas);
        $this->assertContains('contractors', $areas);
        $this->assertContains('drivers', $areas);
        $this->assertContains('leads', $areas);

        $scopes = RoleAccess::mergedVisibilityScopesForUser($user->fresh());
        $this->assertSame('all', $scopes['orders']);
        $this->assertSame('own', $scopes['contractors']);

        $permissions = RoleAccess::userPermissions($user->fresh());
        $this->assertContains('view_orders', $permissions);
        $this->assertContains('assign_drivers', $permissions);
    }
}
