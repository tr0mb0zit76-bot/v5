<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementOperationalCostCategoryResolver;
use App\Support\ManagementCostCategoryCodes;
use App\Support\OwnFleetCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementOperationalCostCategoryResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'orders',
            'contractors',
            'management_expense_categories',
        ]);

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        ManagementExpenseCategory::query()->create([
            'code' => ManagementCostCategoryCodes::HIRED_TRANSPORT,
            'name' => 'Привлечённый транспорт',
            'kind' => 'operational_out_hired',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        ManagementExpenseCategory::query()->create([
            'code' => ManagementCostCategoryCodes::OWN_FLEET,
            'name' => 'Собственный парк',
            'kind' => 'operational_out_own_fleet',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 25,
        ]);
    }

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
