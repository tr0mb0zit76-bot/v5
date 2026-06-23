<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\OwnFleetContractorService;
use App\Support\OwnFleetCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VirtualOwnFleetContractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['contractors']);

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_own_company')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function test_virtual_fleet_contractor_is_not_own_company_profile(): void
    {
        $contractor = Contractor::query()->create([
            'name' => OwnFleetCatalog::CONTRACTOR_NAME,
            'is_own_company' => true,
        ]);

        $this->assertTrue($contractor->isVirtualOwnFleetContractor());
        $this->assertFalse($contractor->isOwnCompanyProfile());
        $this->assertFalse(
            Contractor::query()->ownCompanyProfiles()->whereKey($contractor->id)->exists(),
        );
    }

    public function test_ensure_contractor_clears_own_company_flag(): void
    {
        Contractor::query()->create([
            'name' => OwnFleetCatalog::CONTRACTOR_NAME,
            'is_own_company' => true,
        ]);

        $contractor = app(OwnFleetContractorService::class)->ensureContractor();

        $this->assertNotNull($contractor);
        $this->assertFalse($contractor->is_own_company);
        $this->assertFalse($contractor->isOwnCompanyProfile());
    }

    public function test_regular_own_company_stays_in_own_company_profiles(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Тестовая компания',
            'is_own_company' => true,
        ]);

        $this->assertTrue($contractor->isOwnCompanyProfile());
        $this->assertTrue(
            Contractor::query()->ownCompanyProfiles()->whereKey($contractor->id)->exists(),
        );
    }
}
