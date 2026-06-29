<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class RoleAccessSalesBookTest extends TestCase
{
    public function test_sales_book_visibility_without_granular_permissions_allows_read_only(): void
    {
        $user = $this->makeUserWithRole(
            visibilityAreas: ['scripts', 'sales_assistant_book'],
            permissions: [],
        );

        $this->assertTrue(RoleAccess::canReadSalesBook($user));
        $this->assertFalse(RoleAccess::canCommentSalesBook($user));
        $this->assertFalse(RoleAccess::canWriteSalesBook($user));
    }

    public function test_sales_book_read_permission_allows_read_only(): void
    {
        $user = $this->makeUserWithRole(
            visibilityAreas: ['scripts', 'sales_assistant_book'],
            permissions: ['sales_book_read'],
        );

        $this->assertTrue(RoleAccess::canReadSalesBook($user));
        $this->assertFalse(RoleAccess::canCommentSalesBook($user));
        $this->assertFalse(RoleAccess::canWriteSalesBook($user));
    }

    public function test_sales_book_write_permission_allows_read_and_write(): void
    {
        $user = $this->makeUserWithRole(
            visibilityAreas: ['scripts', 'sales_assistant_book'],
            permissions: ['sales_book_write'],
        );

        $this->assertTrue(RoleAccess::canReadSalesBook($user));
        $this->assertTrue(RoleAccess::canCommentSalesBook($user));
        $this->assertTrue(RoleAccess::canWriteSalesBook($user));
    }

    public function test_scripts_without_book_submodule_does_not_grant_book_visibility(): void
    {
        $areas = RoleAccess::effectiveVisibilityAreasFromRolePayload('manager', [
            'scripts',
            'sales_assistant_scripts',
        ]);

        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'sales_assistant_book'));
        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'sales_assistant_scripts'));
    }

    public function test_legacy_scripts_only_still_grants_all_sales_assistant_submodules(): void
    {
        $areas = RoleAccess::effectiveVisibilityAreasFromRolePayload('manager', ['scripts']);

        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'sales_assistant_book'));
        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'sales_assistant_scripts'));
    }

    /**
     * @param  list<string>  $visibilityAreas
     * @param  list<string>  $permissions
     */
    private function makeUserWithRole(array $visibilityAreas, array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'custom_manager',
            'display_name' => 'Custom manager',
            'permissions' => $permissions,
            'visibility_areas' => $visibilityAreas,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
