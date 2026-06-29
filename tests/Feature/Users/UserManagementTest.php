<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
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

    public function test_admin_can_store_encrypted_mail_imap_password(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $managedUser = User::factory()->create([
            'email' => 'manager@example.com',
            'role_id' => $managerRoleId,
        ]);

        $response = $this->actingAs($admin)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $managerRoleId,
            'is_active' => true,
            'has_signing_authority' => false,
            'mail_password' => 'reg-mail-secret',
            'mail_sync_enabled' => true,
        ]);

        $response->assertRedirect(route('settings.users.index'));

        $managedUser->refresh();

        $this->assertTrue($managedUser->hasMailImapCredential());
        $this->assertSame('reg-mail-secret', $managedUser->mail_imap_secret);
        $this->assertTrue($managedUser->mail_sync_enabled);
        $this->assertNotSame('reg-mail-secret', $managedUser->getRawOriginal('mail_imap_secret'));
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
