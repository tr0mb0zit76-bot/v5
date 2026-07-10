<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LoadingPlannerProject;
use App\Models\Order;
use App\Models\Role;
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

    public function test_supervisor_can_view_managers_personal_unlinked_project(): void
    {
        $manager = User::factory()->create();
        $supervisor = $this->createSupervisorUser();

        $project = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'name' => 'Личный черновик менеджера',
            'status' => 'draft',
        ]);

        $this->assertTrue(LoadingPlannerAccess::canViewAllProjects($supervisor));
        $this->assertTrue(LoadingPlannerAccess::canViewProject($supervisor, $project));
    }

    public function test_order_owner_can_access_project_linked_to_order(): void
    {
        if (! Schema::hasColumn('loading_planner_projects', 'order_id')
            || ! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('order link columns are unavailable.');
        }

        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_owner_id' => $owner->id,
        ]);

        $project = LoadingPlannerProject::query()->create([
            'user_id' => $manager->id,
            'order_id' => $order->id,
            'name' => 'Расчёт владельца сделки',
            'status' => 'draft',
        ]);

        $this->assertTrue(LoadingPlannerAccess::canViewProject($owner, $project->fresh('order')));
    }

    public function test_admin_can_view_all_projects(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], [
            'display_name' => 'Admin',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['modules_how_much_fits'],
        ]);

        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue(LoadingPlannerAccess::canViewAllProjects($admin));
    }

    private function createSupervisorUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['leads', 'orders', 'modules_how_much_fits'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['leads' => 'all', 'orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
