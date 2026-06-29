<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ManagementAccountingAccessTest extends TestCase
{
    public function test_guest_is_redirected_from_management_accounting(): void
    {
        $this->get('/finance/management-accounting')->assertRedirect('/login');
    }

    public function test_user_without_flag_gets_forbidden(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'User',
            'email' => 'user@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => false,
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting')
            ->assertForbidden();
    }

    public function test_user_with_flag_can_open_management_accounting(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Index')
                ->has('analytics')
                ->has('filters'));
    }
}
