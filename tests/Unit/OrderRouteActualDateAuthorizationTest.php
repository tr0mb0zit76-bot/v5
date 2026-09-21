<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\OrderRouteActualDateAuthorization;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderRouteActualDateAuthorizationTest extends TestCase
{
    public function test_anyone_may_set_unloading_actual_for_the_first_time(): void
    {
        $manager = $this->userWithRoles(false, false);

        OrderRouteActualDateAuthorization::assertCanChangeUnloadingActual(
            $manager,
            null,
            '2026-06-02',
        );

        $this->assertTrue(true);
    }

    public function test_manager_cannot_change_or_clear_existing_unloading_actual(): void
    {
        $manager = $this->userWithRoles(false, false);

        $this->expectException(ValidationException::class);

        OrderRouteActualDateAuthorization::assertCanChangeUnloadingActual(
            $manager,
            '2026-06-02',
            '2026-06-03',
        );
    }

    public function test_supervisor_and_admin_may_change_existing_unloading_actual(): void
    {
        $supervisor = $this->userWithRoles(false, true);
        $admin = $this->userWithRoles(true, false);

        OrderRouteActualDateAuthorization::assertCanChangeUnloadingActual(
            $supervisor,
            '2026-06-02',
            'clear',
        );

        OrderRouteActualDateAuthorization::assertCanChangeUnloadingActual(
            $admin,
            '2026-06-02',
            '2026-06-10',
        );

        $this->assertTrue(true);
    }

    public function test_same_date_is_not_treated_as_change(): void
    {
        $manager = $this->userWithRoles(false, false);

        OrderRouteActualDateAuthorization::assertCanChangeUnloadingActual(
            $manager,
            '2026-06-02',
            '2026-06-02',
        );

        $this->assertTrue(true);
    }

    private function userWithRoles(bool $admin, bool $supervisor): User
    {
        $user = new class extends User
        {
            public bool $adminFlag = false;

            public bool $supervisorFlag = false;

            public function isAdmin(): bool
            {
                return $this->adminFlag;
            }

            public function isSupervisor(): bool
            {
                return $this->supervisorFlag;
            }
        };

        $user->adminFlag = $admin;
        $user->supervisorFlag = $supervisor;

        return $user;
    }
}
