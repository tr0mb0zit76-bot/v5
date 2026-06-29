<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\HeadOfSalesInsightsService;
use App\Support\AiAgentCatalog;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeadOfSalesInsightsServiceTest extends TestCase
{
    public function test_denies_access_without_head_of_sales_permission(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_areas' => json_encode(['leads', 'orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'role_id' => $managerRoleId,
            'name' => 'Менеджер',
            'email' => 'manager@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->setRelation('role', Role::query()->find($managerRoleId));
        $user->setRelation('roles', collect());

        $this->assertFalse(RoleAccess::canViewHeadOfSalesInsights($user));

        $result = app(HeadOfSalesInsightsService::class)->insights($user);

        $this->assertFalse($result['available']);
    }

    public function test_supervisor_sees_rodion_persona_and_has_head_of_sales_access(): void
    {
        $supervisorRoleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['reports', 'leads', 'orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'role_id' => $supervisorRoleId,
            'name' => 'Руководитель',
            'email' => 'supervisor@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->setRelation('role', Role::query()->find($supervisorRoleId));
        $user->setRelation('roles', collect());

        $this->assertTrue(RoleAccess::canViewHeadOfSalesInsights($user));

        $slugs = collect(AiAgentCatalog::optionsForUser($user))->pluck('slug')->all();
        $this->assertContains('rodion', $slugs);

        $persona = AiAgentCatalog::resolveForUser($user, 'rodion');
        $this->assertSame('rodion', $persona['slug']);
        $this->assertStringContainsString('руководитель отдела продаж', mb_strtolower($persona['prompt_lead']));
    }
}
