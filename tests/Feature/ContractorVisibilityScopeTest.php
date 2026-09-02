<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class ContractorVisibilityScopeTest extends TestCase
{
    public function test_manager_with_own_contractors_scope_sees_only_own_customers_in_search(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager_test',
            'display_name' => 'Менеджер тест',
            'permissions' => [],
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => [
                'contractors' => 'own',
            ],
        ]);

        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        RoleAccess::syncUserRoles($manager, [$managerRole->id]);

        $ownCustomer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Свой клиент',
            'owner_id' => $manager->id,
            'is_active' => true,
        ]);

        $foreignCustomer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Чужой клиент',
            'owner_id' => User::factory()->create()->id,
            'is_active' => true,
        ]);

        $foreignCarrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Чужой перевозчик',
            'owner_id' => User::factory()->create()->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager);

        $customerResponse = $this->getJson(route('contractors.search', [
            'q' => '',
            'type' => 'customer',
            'limit' => 100,
        ]));

        $customerResponse->assertOk();
        $customerIds = collect($customerResponse->json('contractors'))->pluck('id')->all();

        $this->assertContains($ownCustomer->id, $customerIds);
        $this->assertNotContains($foreignCustomer->id, $customerIds);

        $carrierResponse = $this->getJson(route('contractors.search', [
            'q' => '',
            'type' => 'carrier',
            'limit' => 100,
        ]));

        $carrierResponse->assertOk();
        $carrierIds = collect($carrierResponse->json('contractors'))->pluck('id')->all();

        $this->assertContains($foreignCarrier->id, $carrierIds);
    }

    public function test_manager_with_own_contractors_scope_sees_own_company_profile_even_if_not_owner(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager_own_company_test',
            'display_name' => 'Менеджер own company',
            'permissions' => [],
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => [
                'contractors' => 'own',
            ],
        ]);

        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        RoleAccess::syncUserRoles($manager, [$managerRole->id]);

        $owner = User::factory()->create();

        $ownCompany = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Автоальянс',
            'owner_id' => $owner->id,
            'is_own_company' => true,
            'is_active' => true,
        ]);

        $foreignCustomer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Чужой клиент',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager);

        $searchResponse = $this->getJson(route('contractors.search', [
            'q' => '',
            'type' => 'customer',
            'limit' => 100,
        ]));

        $searchResponse->assertOk();
        $customerIds = collect($searchResponse->json('contractors'))->pluck('id')->all();

        $this->assertContains($ownCompany->id, $customerIds);
        $this->assertNotContains($foreignCustomer->id, $customerIds);

        $this->get(route('contractors.show', $ownCompany))->assertOk();

        $indexResponse = $this->get(route('contractors.index'));
        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn ($page) => $page
            ->where(
                'contractors',
                fn ($contractors) => collect($contractors)->pluck('id')->contains($ownCompany->id),
            ));
    }
}
