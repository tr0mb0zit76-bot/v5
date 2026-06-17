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
                ->has('tnVedCodes')
                ->has('referenceMeta'));

        $this->actingAs($deniedUser)
            ->get(route('modules.import-cost.index'))
            ->assertForbidden();

        $this->actingAs($allowedUser)
            ->postJson(route('modules.import-cost.calculate'), [
                'invoice_amount' => 1000,
                'currency' => 'RUB',
                'tn_ved_code' => '8429520000',
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
}
