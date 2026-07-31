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
    public function selective_modules_parent_does_not_grant_unchecked_children(): void
    {
        $areas = ['modules', 'modules_how_much_fits'];

        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'modules_how_much_fits'));
        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'modules_how_much_costs'));
        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'modules_import_cost'));
        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'modules_proposal_templates'));
        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'modules_catalog'));
    }

    #[Test]
    public function modules_parent_alone_still_grants_all_children(): void
    {
        $this->assertTrue(RoleAccess::hasModulesSubmoduleAccess(['modules'], 'modules_how_much_fits'));
        $this->assertTrue(RoleAccess::hasModulesSubmoduleAccess(['modules'], 'modules_proposal_templates'));
    }

    #[Test]
    public function selective_settings_parent_does_not_grant_unchecked_children(): void
    {
        $areas = ['settings', 'settings_motivation'];

        $this->assertTrue(RoleAccess::hasVisibilityArea($areas, 'settings_motivation'));
        $this->assertFalse(RoleAccess::hasVisibilityArea($areas, 'settings_system'));
        $this->assertTrue(RoleAccess::hasVisibilityArea(['settings'], 'settings_system'));
    }

    #[Test]
    public function selective_own_fleet_parent_does_not_grant_unchecked_children(): void
    {
        $areas = ['own_fleet', 'fleet_trips'];

        $this->assertTrue(RoleAccess::hasOwnFleetSubmoduleAccess($areas, 'fleet_trips'));
        $this->assertFalse(RoleAccess::hasOwnFleetSubmoduleAccess($areas, 'fleet_efficiency'));
        $this->assertTrue(RoleAccess::hasOwnFleetSubmoduleAccess(['own_fleet'], 'fleet_efficiency'));
    }

    #[Test]
    public function selective_scripts_parent_does_not_grant_unchecked_children(): void
    {
        $areas = ['scripts', 'sales_assistant_book'];

        $this->assertTrue(RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_book'));
        $this->assertFalse(RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_trainer'));
        $this->assertFalse(RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_counter'));
        $this->assertTrue(RoleAccess::hasSalesAssistantSubmoduleAccess(['scripts'], 'sales_assistant_trainer'));
    }

    #[Test]
    public function sidebar_cashflow_requires_payment_schedules_not_documents(): void
    {
        $user = new User;

        $this->assertFalse(SidebarMenuCatalog::isKeyAccessibleForAreas('finance-cashflow', ['documents'], $user));
        $this->assertTrue(SidebarMenuCatalog::isKeyAccessibleForAreas('finance-cashflow', ['payment_schedules'], $user));
    }

    #[Test]
    public function sidebar_respects_selective_modules(): void
    {
        $user = new User;
        $areas = ['modules', 'modules_how_much_fits'];

        $this->assertTrue(SidebarMenuCatalog::isKeyAccessibleForAreas('modules-how-much-fits', $areas, $user));
        $this->assertFalse(SidebarMenuCatalog::isKeyAccessibleForAreas('modules-how-much-costs', $areas, $user));
        $this->assertFalse(SidebarMenuCatalog::isKeyAccessibleForAreas('modules-proposal-templates', $areas, $user));
    }

    #[Test]
    public function sidebar_respects_selective_settings(): void
    {
        $user = new User;
        $areas = ['settings', 'settings_motivation'];

        $this->assertTrue(SidebarMenuCatalog::isKeyAccessibleForAreas('motivation', $areas, $user));
        $this->assertFalse(SidebarMenuCatalog::isKeyAccessibleForAreas('users', $areas, $user));
    }
}
