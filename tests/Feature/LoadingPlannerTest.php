<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class LoadingPlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:2RlkzZy95xqIjfCU4N7u8beHmq38hzI5x6z3adnT9CI=']);
    }

    public function test_loading_planner_requires_authentication(): void
    {
        $this->get('/modules/how-much-fits')->assertRedirect('/login');
    }

    public function test_user_with_modules_visibility_can_open_loading_planner(): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
        ], [
            'display_name' => 'Admin',
            'permissions' => [],
            'columns_config' => [],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this
            ->actingAs($user)
            ->get('/modules/how-much-fits')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/HowMuchFits')
                ->has('projects')
                ->has('transportTemplates')
            );
    }
}
