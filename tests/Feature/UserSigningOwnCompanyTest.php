<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserSigningOwnCompanyTest extends TestCase
{
    #[Test]
    public function user_without_restrictions_can_sign_for_any_own_company(): void
    {
        if (! Schema::hasTable('user_signing_own_company')) {
            $this->markTestSkipped('user_signing_own_company table not migrated');
        }

        $user = User::factory()->create(['has_signing_authority' => true]);

        $this->assertTrue($user->signingOwnCompaniesUnrestricted());
        $this->assertTrue($user->canSignDocumentsForOwnCompany(10));
        $this->assertTrue($user->canSignDocumentsForOwnCompany(99));
    }

    #[Test]
    public function user_with_selected_companies_can_sign_only_for_them(): void
    {
        if (! Schema::hasTable('user_signing_own_company') || ! Schema::hasColumn('contractors', 'is_own_company')) {
            $this->markTestSkipped('Required tables not migrated');
        }

        $companyA = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Альфа',
            'is_own_company' => true,
        ]);
        $companyB = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Бета',
            'is_own_company' => true,
        ]);

        $user = User::factory()->create(['has_signing_authority' => true]);
        $user->signingOwnCompanies()->sync([$companyA->id]);

        $this->assertFalse($user->signingOwnCompaniesUnrestricted());
        $this->assertTrue($user->canSignDocumentsForOwnCompany($companyA->id));
        $this->assertFalse($user->canSignDocumentsForOwnCompany($companyB->id));
    }

    #[Test]
    public function admin_can_save_signing_own_company_ids_for_user(): void
    {
        if (! Schema::hasTable('user_signing_own_company') || ! Schema::hasColumn('contractors', 'is_own_company')) {
            $this->markTestSkipped('Required tables not migrated');
        }

        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $employee = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'has_signing_authority' => false]);

        $company = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Гамма',
            'is_own_company' => true,
        ]);

        $this->actingAs($admin)->patch(route('users.update', $employee), [
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => '',
            'role_id' => $employee->role_id,
            'is_active' => true,
            'has_signing_authority' => true,
            'signing_own_company_ids' => [$company->id],
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect();

        $employee->refresh();
        $employee->load('signingOwnCompanies');

        $this->assertTrue($employee->has_signing_authority);
        $this->assertSame([$company->id], $employee->signingOwnCompanyIds());
        $this->assertTrue($employee->canSignDocumentsForOwnCompany($company->id));
    }
}
