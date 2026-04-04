<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
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
            $table->json('columns_config')->nullable();
            $table->boolean('has_signing_authority')->default(false);
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
            $table->boolean('has_signing_authority')->default(false);
            $table->json('ai_preferences')->nullable();
            $table->boolean('ai_learning_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_admin_can_open_user_management_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');

        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        User::factory()->create(['role_id' => $managerRoleId, 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('settings.users.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users', 2)
            ->has('roles', 2)
            ->where('roles.1.default_has_signing_authority', false)
        );
    }

    public function test_non_admin_cannot_open_user_management_page(): void
    {
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $manager = User::factory()->create(['role_id' => $managerRoleId]);

        $response = $this->actingAs($manager)->get(route('settings.users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Новый менеджер',
            'email' => 'new-manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $managerRoleId,
            'is_active' => true,
            'has_signing_authority' => true,
        ]);

        $response->assertRedirect(route('settings.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new-manager@example.com',
            'role_id' => $managerRoleId,
            'is_active' => true,
            'has_signing_authority' => true,
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $viewerRoleId = $this->createRole('viewer', 'Только просмотр');
        $managerRoleId = $this->createRole('manager', 'Менеджер');

        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $managedUser = User::factory()->create([
            'name' => 'Старое имя',
            'email' => 'managed@example.com',
            'role_id' => $viewerRoleId,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('users.update', $managedUser), [
            'name' => 'Новое имя',
            'email' => 'managed@example.com',
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $managerRoleId,
            'is_active' => false,
            'has_signing_authority' => true,
        ]);

        $response->assertRedirect(route('settings.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Новое имя',
            'role_id' => $managerRoleId,
            'is_active' => false,
            'has_signing_authority' => true,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    private function createRole(string $name, string $displayName): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
