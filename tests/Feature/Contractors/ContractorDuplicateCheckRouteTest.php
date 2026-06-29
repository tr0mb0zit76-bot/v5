<?php

namespace Tests\Feature\Contractors;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractorDuplicateCheckRouteTest extends TestCase
{
    public function test_duplicate_check_route_is_not_shadowed_by_contractor_show(): void
    {
        $admin = $this->createAdminUser();

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Тест',
            'inn' => '2543143277',
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.duplicate-check', [
            'inn' => '2543143277',
            'name' => 'ООО Тест',
            'ignore_id' => $contractor->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('duplicate', false);
    }

    private function createAdminUser(): User
    {
        $adminRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $adminRoleId,
        ]);
    }
}
