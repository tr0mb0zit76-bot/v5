<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LoadingPlannerProject;
use App\Models\User;
use App\Support\LoadingPlannerAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoadingPlannerAccessTest extends TestCase
{
    public function test_owner_can_view_personal_project(): void
    {
        $user = User::factory()->create();
        $project = new LoadingPlannerProject([
            'user_id' => $user->id,
            'name' => 'Личный',
        ]);

        $this->assertTrue(LoadingPlannerAccess::canViewProject($user, $project));
    }

    public function test_supervisor_can_view_project_linked_to_accessible_lead(): void
    {
        if (! Schema::hasColumn('loading_planner_projects', 'lead_id')) {
            $this->markTestSkipped('loading_planner_projects.lead_id is unavailable.');
        }

        $manager = User::factory()->create();
        $supervisor = $this->createSupervisorUser();
        $lead = Lead::factory()->create(['responsible_id' => $manager->id]);

        $project = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'lead_id' => $lead->id,
            'name' => 'По лиду',
            'status' => 'draft',
        ]);

        $this->assertTrue(LoadingPlannerAccess::canViewProject($supervisor, $project->fresh('lead')));
    }

    private function createSupervisorUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor-access-test',
            'visibility_areas' => json_encode(['leads', 'orders', 'modules_how_much_fits'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['leads' => 'all', 'orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
