<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('site_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('theme', 20)->default('light');
            $table->boolean('is_active')->default(true);
            $table->json('ai_preferences')->nullable();
            $table->boolean('ai_learning_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_admin_can_open_role_management_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор', ['manage_roles'], ['dashboard', 'roles']);
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.roles.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Index')
            ->has('roles', 1)
            ->has('permissionOptions')
            ->has('visibilityAreaOptions')
            ->has('visibilityScopeOptions', 2)
        );
    }

    public function test_non_admin_cannot_open_role_management_page(): void
    {
        $managerRoleId = $this->createRole('manager', 'Менеджер', ['view_orders'], ['dashboard', 'orders']);
        $manager = User::factory()->create(['role_id' => $managerRoleId]);

        $response = $this->actingAs($manager)->get(route('settings.roles.index'));

        $response->assertForbidden();
    }

    public function test_visibility_area_blocks_hidden_section(): void
    {
        $viewerRoleId = $this->createRole('viewer', 'Только просмотр', ['view_orders'], ['dashboard', 'orders']);
        $viewer = User::factory()->create(['role_id' => $viewerRoleId]);

        $response = $this->actingAs($viewer)->get(route('documents.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_role_with_visibility_areas(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор', ['manage_roles'], ['dashboard', 'roles']);
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'auditor',
            'display_name' => 'Аудитор',
            'description' => 'Просмотр отчетов и документов',
            'permissions' => ['view_reports', 'view_documents'],
            'visibility_areas' => ['dashboard', 'reports', 'documents'],
            'visibility_scopes' => [
                'dashboard' => ['mode' => 'all'],
                'reports' => ['mode' => 'all'],
                'documents' => ['mode' => 'all'],
            ],
        ]);

        $response->assertRedirect(route('settings.roles.index'));
        $this->assertDatabaseHas('roles', [
            'name' => 'auditor',
            'display_name' => 'Аудитор',
        ]);
    }

    public function test_admin_can_update_role_visibility_areas(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор', ['manage_roles'], ['dashboard', 'roles']);
        $roleId = $this->createRole('viewer', 'Только просмотр', ['view_orders'], ['dashboard', 'orders']);
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('roles.update', $roleId), [
            'name' => 'viewer',
            'display_name' => 'Только просмотр',
            'description' => 'Обновленное описание',
            'permissions' => ['view_orders', 'view_documents'],
            'visibility_areas' => ['dashboard', 'orders', 'documents'],
            'visibility_scopes' => [
                'dashboard' => ['mode' => 'all'],
                'orders' => ['mode' => 'all'],
                'documents' => ['mode' => 'all'],
            ],
        ]);

        $response->assertRedirect(route('settings.roles.index'));

        $updatedRole = DB::table('roles')->where('id', $roleId)->first();

        $this->assertSame('Обновленное описание', $updatedRole->description);
        $this->assertSame(['dashboard', 'orders', 'documents'], json_decode($updatedRole->visibility_areas, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('all', json_decode($updatedRole->visibility_scopes, true, 512, JSON_THROW_ON_ERROR)['orders']);
    }

    public function test_admin_can_update_role_when_visibility_scopes_column_is_missing(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор', ['manage_roles'], ['dashboard', 'roles']);
        $roleId = $this->createRole('clerk', 'Делопроизводитель', ['view_orders'], ['dashboard', 'orders']);
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('visibility_scopes');
        });

        $response = $this->actingAs($admin)->patch(route('roles.update', $roleId), [
            'name' => 'clerk',
            'display_name' => 'Делопроизводитель',
            'description' => 'Обновлено без колонки scopes',
            'permissions' => ['view_orders'],
            'visibility_areas' => ['dashboard', 'orders', 'documents'],
            'visibility_scopes' => [
                'orders' => ['mode' => 'all'],
            ],
        ]);

        $response->assertRedirect(route('settings.roles.index'));

        $updatedRole = DB::table('roles')->where('id', $roleId)->first();

        $this->assertSame('Обновлено без колонки scopes', $updatedRole->description);
        $this->assertSame(['dashboard', 'orders', 'documents'], json_decode($updatedRole->visibility_areas, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_admin_cannot_delete_role_assigned_to_users(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор', ['manage_roles'], ['dashboard', 'roles']);
        $managerRoleId = $this->createRole('manager', 'Менеджер', ['view_orders'], ['dashboard', 'orders']);
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        User::factory()->create(['role_id' => $managerRoleId]);

        $response = $this->actingAs($admin)->delete(route('roles.destroy', $managerRoleId));

        $response->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $managerRoleId]);
    }

    private function createRole(string $name, string $displayName, array $permissions, array $visibilityAreas, array $visibilityScopes = []): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'visibility_areas' => json_encode($visibilityAreas, JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode($visibilityScopes, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
