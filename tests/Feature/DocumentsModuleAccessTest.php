<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class DocumentsModuleAccessTest extends TestCase
{
    public function test_admin_can_open_documents_registry(): void
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'permissions' => [],
            'visibility_areas' => ['dashboard'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $user->setRelation('roles', collect());

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_user_with_orders_visibility_can_open_documents_registry(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => ['dashboard', 'orders'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_user_without_documents_or_orders_gets_forbidden(): void
    {
        $role = Role::query()->create([
            'name' => 'viewer',
            'display_name' => 'Наблюдатель',
            'permissions' => [],
            'visibility_areas' => ['dashboard'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertForbidden();
    }

    public function test_can_access_visibility_area_treats_admin_by_primary_role_id(): void
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'permissions' => [],
            'visibility_areas' => RoleAccess::visibilityAreaKeys(),
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $user->setRelation('roles', collect());

        $this->assertTrue(RoleAccess::canAccessVisibilityArea($user, 'documents'));
        $this->assertTrue(RoleAccess::canAccessAnyVisibilityArea($user, ['documents', 'orders']));
    }
}
