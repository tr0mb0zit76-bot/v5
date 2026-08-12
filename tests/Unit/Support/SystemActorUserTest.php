<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemActorUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemActorUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_named_system_user_with_management_accounting(): void
    {
        if (Schema::hasTable('roles')) {
            Role::query()->firstOrCreate(
                ['name' => 'admin'],
                [
                    'display_name' => 'Admin',
                    'visibility_areas' => ['orders'],
                ],
            );
        }

        config([
            'one_c.system_actor' => [
                'user_id' => null,
                'email' => 'system@crm.local',
                'name' => 'Система',
            ],
        ]);

        $first = SystemActorUser::resolve();
        $second = SystemActorUser::resolve();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Система', $first->name);
        $this->assertSame('system@crm.local', $first->email);
        $this->assertTrue((bool) $first->can_management_accounting);
        $this->assertTrue((bool) $first->is_active);
        $this->assertTrue($first->isAdmin());
    }

    public function test_uses_configured_user_id_when_present(): void
    {
        $existing = User::factory()->create([
            'name' => 'Old',
            'email' => 'custom-system@example.test',
            'can_management_accounting' => false,
            'is_active' => true,
        ]);

        if (Schema::hasTable('roles')) {
            $admin = Role::query()->firstOrCreate(
                ['name' => 'admin'],
                [
                    'display_name' => 'Admin',
                    'visibility_areas' => ['orders'],
                ],
            );
            $existing->role_id = $admin->id;
            $existing->save();
        }

        config([
            'one_c.system_actor' => [
                'user_id' => $existing->id,
                'email' => 'system@crm.local',
                'name' => 'Система',
            ],
        ]);

        $resolved = SystemActorUser::resolve();

        $this->assertSame($existing->id, $resolved->id);
        $this->assertTrue((bool) $resolved->can_management_accounting);
    }
}
