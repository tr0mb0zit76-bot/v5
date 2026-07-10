<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Support\LeadViewAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadViewAuthorizationTest extends TestCase
{
    public function test_department_scope_allows_colleague_lead(): void
    {
        if (! Schema::hasTable('department_user') || ! Schema::hasTable('departments')) {
            $this->markTestSkipped('department tables are unavailable.');
        }

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Leads dept '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'dept_leads_'.uniqid(),
        ], [
            'display_name' => 'Dept leads',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['leads'],
            'visibility_scopes' => ['leads' => 'department'],
        ]);

        $role->update([
            'visibility_areas' => ['leads'],
            'visibility_scopes' => ['leads' => 'department'],
        ]);

        $colleague = User::factory()->create(['role_id' => $role->id]);
        $viewer = User::factory()->create(['role_id' => $role->id]);

        DB::table('department_user')->insert([
            [
                'department_id' => $departmentId,
                'user_id' => $colleague->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department_id' => $departmentId,
                'user_id' => $viewer->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $lead = new Lead([
            'responsible_id' => $colleague->id,
        ]);

        $this->assertTrue(LeadViewAuthorization::userCanViewLead($viewer, $lead));
    }
}
