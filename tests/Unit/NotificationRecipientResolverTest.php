<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\User;
use App\Services\Notifications\NotificationRecipientResolver;
use App\Support\UserDepartmentSync;
use Tests\TestCase;

class NotificationRecipientResolverTest extends TestCase
{
    public function test_resolves_approval_recipients_for_primary_department_only(): void
    {
        $departmentA = Department::query()->where('sort_order', 1)->firstOrFail();
        $departmentB = Department::query()->where('sort_order', 2)->firstOrFail();

        $manager = User::factory()->create();
        UserDepartmentSync::sync($manager, (int) $departmentA->id, []);

        $supervisorA = User::factory()->create();
        UserDepartmentSync::sync($supervisorA, (int) $departmentA->id, [(int) $departmentA->id]);

        $supervisorB = User::factory()->create();
        UserDepartmentSync::sync($supervisorB, (int) $departmentB->id, [(int) $departmentB->id]);

        $resolver = app(NotificationRecipientResolver::class);
        $recipients = $resolver->approvalRecipientsForUser($manager);

        $this->assertCount(1, $recipients);
        $this->assertTrue($recipients->contains(fn (User $user): bool => $user->is($supervisorA)));
        $this->assertFalse($recipients->contains(fn (User $user): bool => $user->is($supervisorB)));
    }

    public function test_chief_supervisor_can_receive_approvals_for_multiple_departments(): void
    {
        $departmentA = Department::query()->where('sort_order', 1)->firstOrFail();
        $departmentB = Department::query()->where('sort_order', 2)->firstOrFail();

        $manager = User::factory()->create();
        UserDepartmentSync::sync($manager, (int) $departmentB->id, []);

        $chief = User::factory()->create(['belongs_to_management' => true]);
        UserDepartmentSync::sync($chief, (int) $departmentA->id, [
            (int) $departmentA->id,
            (int) $departmentB->id,
        ]);

        $resolver = app(NotificationRecipientResolver::class);
        $recipients = $resolver->approvalRecipientsForUser($manager);

        $this->assertCount(1, $recipients);
        $this->assertTrue($recipients->contains(fn (User $user): bool => $user->is($chief)));
    }
}
