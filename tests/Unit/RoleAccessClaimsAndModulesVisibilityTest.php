<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\RoleAccess;
use App\Support\SidebarMenuCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAccessClaimsAndModulesVisibilityTest extends TestCase
{
    #[Test]
    public function claims_is_in_visibility_catalog_and_manager_defaults(): void
    {
        $this->assertContains('claims', RoleAccess::visibilityAreaKeys());
        $this->assertContains('claims', RoleAccess::defaultVisibilityAreas('manager'));
        $this->assertNotContains('claims', RoleAccess::defaultVisibilityAreas('viewer'));
    }

    #[Test]
    public function orders_without_claims_does_not_grant_claims_area(): void
    {
        $this->assertFalse(RoleAccess::hasVisibilityArea(['orders'], 'claims'));
        $this->assertTrue(RoleAccess::hasVisibilityArea(['claims'], 'claims'));
    }

    #[Test]
    public function proposal_templates_are_not_implied_by_settings_system(): void
    {
        $this->assertFalse(RoleAccess::hasVisibilityArea(['settings_system'], 'modules_proposal_templates'));
        $this->assertFalse(RoleAccess::hasVisibilityArea(['settings'], 'modules_proposal_templates'));
        $this->assertTrue(RoleAccess::hasVisibilityArea(['modules_proposal_templates'], 'modules_proposal_templates'));
        $this->assertTrue(RoleAccess::hasVisibilityArea(['modules'], 'modules_proposal_templates'));
    }

    #[Test]
    public function sidebar_cashflow_requires_payment_schedules_not_documents(): void
    {
        $user = new User;

        $this->assertFalse(SidebarMenuCatalog::isKeyAccessibleForAreas('finance-cashflow', ['documents'], $user));
        $this->assertTrue(SidebarMenuCatalog::isKeyAccessibleForAreas('finance-cashflow', ['payment_schedules'], $user));
    }
}
