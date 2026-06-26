<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTrackReceivedDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_clerk_can_edit_track_received_dates(): void
    {
        $role = Role::query()->create([
            'name' => 'clerk',
            'display_name' => 'Делопроизводитель',
            'permissions' => [],
            'visibility_areas' => ['documents'],
        ]);

        $user = User::factory()->make(['role_id' => $role->id]);
        $user->setRelation('role', $role);

        $this->assertTrue(RoleAccess::canEditTrackReceivedDates($user));
    }

    public function test_manager_cannot_edit_track_received_dates(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => ['documents'],
        ]);

        $user = User::factory()->make(['role_id' => $role->id]);
        $user->setRelation('role', $role);

        $this->assertFalse(RoleAccess::canEditTrackReceivedDates($user));
    }
}
