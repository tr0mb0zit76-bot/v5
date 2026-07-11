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
    public function sales_book_write_implies_read_and_comment(): void
    {
        $role = Role::query()->create([
            'name' => 'sales_book_editor',
            'permissions' => ['sales_book_write'],
            'visibility_areas' => ['sales_assistant_book'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue(RoleAccess::userHasPermission($user, 'sales_book_read'));
        $this->assertTrue(RoleAccess::userHasPermission($user, 'sales_book_comment'));
        $this->assertTrue(RoleAccess::userHasPermission($user, 'sales_book_write'));
    }

    #[Test]
    public function legacy_order_permissions_are_not_in_catalog(): void
    {
        $keys = RoleAccess::permissionKeys();

        $this->assertNotContains('view_orders', $keys);
        $this->assertNotContains('create_orders', $keys);
        $this->assertNotContains('edit_orders', $keys);
    }
}
