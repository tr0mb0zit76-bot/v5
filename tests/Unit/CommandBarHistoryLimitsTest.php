<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\CommandBarHistoryLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommandBarHistoryLimitsTest extends TestCase
{
    #[Test]
    public function it_returns_default_limits_for_regular_user(): void
    {
        $user = $this->userWithRole('manager');

        $profile = CommandBarHistoryLimits::profileForUser($user);

        $this->assertSame('default', $profile['tier']);
        $this->assertSame(40, $profile['storage']);
        $this->assertSame(20, $profile['request']);
        $this->assertSame(10, $profile['llm']);
        $this->assertTrue($profile['can_extend']);
        $this->assertSame(40, CommandBarHistoryLimits::requestMax($user, true));
    }

    #[Test]
    public function it_returns_higher_limits_for_supervisor(): void
    {
        $user = $this->userWithRole('supervisor');

        $profile = CommandBarHistoryLimits::profileForUser($user);

        $this->assertSame('supervisor', $profile['tier']);
        $this->assertSame(80, $profile['storage']);
        $this->assertSame(40, $profile['request']);
        $this->assertSame(20, $profile['llm']);
        $this->assertSame(80, CommandBarHistoryLimits::requestMax($user, true));
        $this->assertSame(40, CommandBarHistoryLimits::llmMax($user, true));
    }

    #[Test]
    public function it_returns_admin_limits_without_extend_toggle_need(): void
    {
        $user = $this->userWithRole('admin');

        $profile = CommandBarHistoryLimits::profileForUser($user);

        $this->assertSame('admin', $profile['tier']);
        $this->assertSame(200, $profile['storage']);
        $this->assertSame(100, $profile['request']);
        $this->assertSame(50, $profile['llm']);
        $this->assertFalse($profile['can_extend']);
        $this->assertSame(100, CommandBarHistoryLimits::requestMax($user, false));
        $this->assertSame(50, CommandBarHistoryLimits::llmMax($user, false));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'display_name' => ucfirst($roleName),
            'visibility_areas' => ['orders'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
