<?php

namespace Tests\Unit\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAccessImpliedPermissionsTest extends TestCase
{
    #[Test]
    public function edit_orders_implies_view_orders(): void
    {
        $role = Role::query()->create([
            'name' => 'order_editor',
            'permissions' => ['edit_orders'],
            'visibility_areas' => ['orders'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue(RoleAccess::userHasPermission($user, 'view_orders'));
        $this->assertTrue(RoleAccess::userHasPermission($user, 'edit_orders'));
    }
}
