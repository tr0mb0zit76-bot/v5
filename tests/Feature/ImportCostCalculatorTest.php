<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportCostCalculatorTest extends TestCase
{
    public function test_module_requires_visibility_area(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Таблицы roles или users недоступны.');
        }

        $allowedRole = Role::query()->firstOrCreate(
            ['name' => 'import_cost_tester'],
            [
                'display_name' => 'Import cost tester',
                'visibility_areas' => ['modules_import_cost'],
            ],
        );
        $allowedRole->update(['visibility_areas' => ['modules_import_cost']]);

        $deniedRole = Role::query()->firstOrCreate(
            ['name' => 'import_cost_denied'],
            [
                'display_name' => 'Import cost denied',
                'visibility_areas' => ['orders'],
            ],
        );
        $deniedRole->update(['visibility_areas' => ['orders']]);

        $allowedUser = User::factory()->create(['role_id' => $allowedRole->id]);
        $deniedUser = User::factory()->create(['role_id' => $deniedRole->id]);

        $this->actingAs($allowedUser)
            ->get(route('modules.import-cost.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/ImportCostCalculator')
                ->has('referenceMeta')
                ->missing('tnVedCodes'));

        $this->actingAs($deniedUser)
            ->get(route('modules.import-cost.index'))
            ->assertForbidden();

        $this->actingAs($allowedUser)
            ->postJson(route('modules.import-cost.calculate'), [
                'invoice_amount' => 1000,
                'currency' => 'RUB',
                'tn_ved_code' => '8429520000',
                'include_utilization_fee' => false,
            ])
            ->assertOk()
            ->assertJsonStructure(['summary', 'breakdown']);

        $this->actingAs($deniedUser)
            ->postJson(route('modules.import-cost.calculate'), [
                'invoice_amount' => 1000,
                'currency' => 'RUB',
                'tn_ved_code' => '8429520000',
            ])
            ->assertForbidden();
    }

    public function test_tn_ved_search_returns_matching_codes(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Таблицы roles или users недоступны.');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'import_cost_search_tester'],
            [
                'display_name' => 'Import cost search tester',
                'visibility_areas' => ['modules_import_cost'],
            ],
        );
        $role->update(['visibility_areas' => ['modules_import_cost']]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->getJson(route('modules.import-cost.tn-ved.search', ['q' => '8429']))
            ->assertOk()
            ->assertJsonStructure(['items'])
            ->assertJson(fn ($json) => $json->has('items')->etc());
    }
}
