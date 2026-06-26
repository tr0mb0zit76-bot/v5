<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class RoleAccessExportGridTest extends TestCase
{
    public function test_guest_cannot_export_grid(): void
    {
        $this->assertFalse(RoleAccess::canExportGrid(null));
    }

    public function test_admin_can_export_grid(): void
    {
        $user = $this->makeUserWithRole('admin', []);

        $this->assertTrue(RoleAccess::canExportGrid($user));
    }

    public function test_supervisor_can_export_grid(): void
    {
        $user = $this->makeUserWithRole('supervisor', []);

        $this->assertTrue(RoleAccess::canExportGrid($user));
    }

    public function test_user_with_view_reports_permission_can_export_grid(): void
    {
        $user = $this->makeUserWithRole('manager', ['view_reports']);

        $this->assertTrue(RoleAccess::canExportGrid($user));
    }

    public function test_regular_manager_cannot_export_grid(): void
    {
        $user = $this->makeUserWithRole('manager', []);

        $this->assertFalse(RoleAccess::canExportGrid($user));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeUserWithRole(string $roleName, array $permissions): User
    {
        $role = new Role([
            'name' => $roleName,
            'display_name' => ucfirst($roleName),
            'permissions' => $permissions,
            'visibility_areas' => ['contractors', 'orders'],
        ]);

        $user = new User;
        $user->role_id = 1;
        $user->setRelation('role', $role);

        return $user;
    }
}
