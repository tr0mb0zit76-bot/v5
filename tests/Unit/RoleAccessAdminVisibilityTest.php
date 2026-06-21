<?php

namespace Tests\Unit;

use App\Support\RoleAccess;
use Tests\TestCase;

class RoleAccessAdminVisibilityTest extends TestCase
{
    public function test_admin_role_effective_visibility_areas_include_full_catalog(): void
    {
        $partialStoredAreas = [
            'dashboard',
            'orders',
            'documents',
            'activities',
            'settings',
        ];

        $areas = RoleAccess::effectiveVisibilityAreasFromRolePayload('admin', $partialStoredAreas);

        foreach (RoleAccess::visibilityAreaKeys() as $key) {
            $this->assertContains($key, $areas, "Missing visibility area: {$key}");
        }
    }

    public function test_legacy_settings_grants_settings_system_access(): void
    {
        $areas = ['dashboard', 'settings', 'settings_motivation'];

        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'settings_system'));
        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'settings_motivation'));
    }

    public function test_explicit_settings_system_grants_access(): void
    {
        $areas = ['dashboard', 'settings_system'];

        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'settings_system'));
    }
}
