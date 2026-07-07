<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Tests\TestCase;

class RoleAccessTrackReceivedDatesTest extends TestCase
{
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

    public function test_clerk_order_inline_editable_fields_include_track_numbers(): void
    {
        $role = Role::query()->create([
            'name' => 'clerk',
            'display_name' => 'Делопроизводитель',
            'permissions' => [],
            'visibility_areas' => ['orders'],
        ]);

        $user = User::factory()->make(['role_id' => $role->id]);
        $user->setRelation('role', $role);

        $fields = RoleAccess::orderInlineEditableFieldsForUser($user);

        $this->assertContains('track_number_customer', $fields);
        $this->assertContains('track_number_carrier', $fields);
    }
}
