<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAccessPaymentReconcileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['role_user', 'users', 'roles']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('can_management_accounting')->default(false);
            $table->timestamps();
        });
    }

    public function test_accountant_default_visibility_includes_payment_reconcile(): void
    {
        $this->assertContains(
            'finance_payment_reconcile',
            RoleAccess::defaultVisibilityAreas('accountant'),
        );
    }

    public function test_accountant_role_can_access_payment_reconcile_without_management_flag(): void
    {
        $role = Role::query()->create([
            'name' => 'accountant',
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('accountant'),
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'password' => bcrypt('secret'),
            'can_management_accounting' => false,
        ]);

        $this->assertTrue(RoleAccess::canAccessPaymentReconcile($user));
    }

    public function test_management_accounting_flag_grants_payment_reconcile(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('secret'),
            'can_management_accounting' => true,
        ]);

        $this->assertTrue(RoleAccess::canAccessPaymentReconcile($user));
    }

    public function test_user_without_area_or_flag_cannot_access_payment_reconcile(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'visibility_areas' => ['documents', 'payment_schedules'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Manager',
            'email' => 'manager2@example.com',
            'password' => bcrypt('secret'),
            'can_management_accounting' => false,
        ]);

        $this->assertFalse(RoleAccess::canAccessPaymentReconcile($user));
    }
}
