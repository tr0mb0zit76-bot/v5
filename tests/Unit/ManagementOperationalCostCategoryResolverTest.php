<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\ManagementAccounting\ManagementOperationalCostCategoryResolver;
use App\Support\ManagementCostCategoryCodes;
use App\Support\OwnFleetCatalog;
use Tests\TestCase;

class ManagementOperationalCostCategoryResolverTest extends TestCase
{
    public function test_resolves_hired_transport_by_default(): void
    {
        $resolver = app(ManagementOperationalCostCategoryResolver::class);

        $this->assertSame(
            ManagementCostCategoryCodes::HIRED_TRANSPORT,
            $resolver->categoryCodeForCarrier(null, null),
        );
    }

    public function test_resolves_own_fleet_for_own_fleet_contractor(): void
    {
        $contractor = Contractor::query()->create([
            'name' => OwnFleetCatalog::CONTRACTOR_NAME,
            'type' => 'carrier',
            'is_active' => true,
        ]);

        $resolver = app(ManagementOperationalCostCategoryResolver::class);

        $this->assertSame(
            ManagementCostCategoryCodes::OWN_FLEET,
            $resolver->categoryCodeForCarrier(null, $contractor->id),
        );
    }
}
