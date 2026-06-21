<?php

namespace Tests\Unit;

use App\Support\RoleAccess;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAccessModulesTest extends TestCase
{
    #[Test]
    public function it_includes_how_much_costs_in_module_component_keys(): void
    {
        $this->assertContains('modules_how_much_costs', RoleAccess::modulesComponentKeys());
    }

    #[Test]
    public function it_expands_legacy_modules_area_with_how_much_costs(): void
    {
        $expanded = RoleAccess::expandLegacyModulesVisibilityAreas(['modules']);

        $this->assertContains('modules_how_much_costs', $expanded);
    }

    #[Test]
    public function it_includes_sales_assistant_counter_in_component_keys(): void
    {
        $this->assertContains('sales_assistant_counter', RoleAccess::salesAssistantComponentKeys());
    }

    #[Test]
    public function it_expands_legacy_scripts_area_with_counter(): void
    {
        $expanded = RoleAccess::expandLegacySalesAssistantVisibilityAreas(['scripts']);

        $this->assertContains('sales_assistant_counter', $expanded);
    }
}
