<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    public function test_admin_can_open_settings_hub(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('sections', 8)
        );
    }

    public function test_admin_can_open_templates_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Templates')
            ->has('templates', 0)
            ->has('documentTypeOptions')
            ->has('sourceTypeOptions')
        );
    }

    public function test_admin_can_open_dictionaries_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        DB::table('contractor_activity_types')->insert([
            'name' => 'Экспедирование',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('settings.dictionaries.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Dictionaries')
            ->has('dictionaries', 4)
            ->where('dictionaries.0.key', 'contractor-activity-types')
            ->where('dictionaries.0.items.0.name', 'Экспедирование')
            ->where('dictionaries.1.key', 'currencies')
            ->has('dictionaries.1.items', 0)
            ->where('dictionaries.2.key', 'vat-rates')
            ->where('dictionaries.3.key', 'departments')
        );
    }

    public function test_admin_can_manage_activity_type_dictionary(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $createResponse = $this->actingAs($admin)->post(route('settings.dictionaries.activity-types.store'), [
            'name' => 'Контейнерные перевозки',
        ]);

        $createResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseHas('contractor_activity_types', [
            'name' => 'Контейнерные перевозки',
        ]);

        $activityTypeId = DB::table('contractor_activity_types')
            ->where('name', 'Контейнерные перевозки')
            ->value('id');

        $deleteResponse = $this->actingAs($admin)->delete(route('settings.dictionaries.activity-types.destroy', $activityTypeId));

        $deleteResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseMissing('contractor_activity_types', [
            'id' => $activityTypeId,
        ]);
    }

    public function test_admin_can_manage_currency_dictionary(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $createResponse = $this->actingAs($admin)->post(route('settings.dictionaries.currencies.store'), [
            'code' => 'gbp',
            'name' => 'Фунт стерлингов',
        ]);

        $createResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseHas('currencies', [
            'code' => 'GBP',
            'name' => 'Фунт стерлингов',
        ]);

        $currencyId = DB::table('currencies')
            ->where('code', 'GBP')
            ->value('id');

        $deleteResponse = $this->actingAs($admin)->delete(route('settings.dictionaries.currencies.destroy', $currencyId));

        $deleteResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseMissing('currencies', [
            'id' => $currencyId,
        ]);
    }

    public function test_admin_can_manage_vat_rate_dictionary(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $createResponse = $this->actingAs($admin)->post(route('settings.dictionaries.vat-rates.store'), [
            'rate_percent' => 7,
            'label' => 'С НДС 7% (тест)',
        ]);

        $createResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseHas('vat_rates', [
            'code' => 'vat_7',
            'label' => 'С НДС 7% (тест)',
        ]);

        $vatRateId = DB::table('vat_rates')
            ->where('code', 'vat_7')
            ->value('id');

        $deleteResponse = $this->actingAs($admin)->delete(route('settings.dictionaries.vat-rates.destroy', $vatRateId));

        $deleteResponse->assertRedirect(route('settings.dictionaries.index'));

        $this->assertDatabaseMissing('vat_rates', [
            'id' => $vatRateId,
        ]);
    }

    public function test_admin_can_open_table_management_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.tables.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Tables')
            ->has('roles', 2)
            ->has('orderColumns')
            ->has('leadColumns')
            ->has('contractorColumns')
            ->has('paymentScheduleColumns')
            ->where('orderColumns.0.field', 'id')
            ->where('leadColumns', fn ($columns) => collect($columns)->contains(
                fn ($column) => ($column['field'] ?? null) === 'number'
            ))
            ->where('contractorColumns', fn ($columns) => collect($columns)->contains(
                fn ($column) => ($column['field'] ?? null) === 'name'
            ))
            ->where('paymentScheduleColumns', fn ($columns) => collect($columns)->contains(
                fn ($column) => ($column['field'] ?? null) === 'order_number'
            ))
        );
    }

    public function test_admin_can_update_role_order_table_preset(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.tables.update', $managerRoleId), [
            'table' => 'orders',
            'columns' => [
                ['colId' => 'order_number', 'hide' => false, 'width' => 100, 'order' => 0],
                ['colId' => 'manager_name', 'hide' => false, 'width' => 160, 'order' => 1],
                ['colId' => 'salary_paid', 'hide' => true, 'width' => 120, 'order' => 2],
            ],
        ]);

        $response->assertRedirect(route('settings.tables.index'));

        $role = DB::table('roles')->where('id', $managerRoleId)->first();
        $columnsConfig = json_decode($role->columns_config, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['hide' => false, 'colId' => 'order_number', 'order' => 0, 'width' => 100],
            ['hide' => false, 'colId' => 'manager_name', 'order' => 1, 'width' => 160],
            ['hide' => true, 'colId' => 'salary_paid', 'order' => 2, 'width' => 120],
        ], $columnsConfig['orders']);
    }

    public function test_admin_can_update_role_lead_table_preset(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.tables.update', $managerRoleId), [
            'table' => 'leads',
            'columns' => [
                ['colId' => 'number', 'hide' => false, 'width' => 110, 'order' => 0],
                ['colId' => 'title', 'hide' => false, 'width' => 240, 'order' => 1],
                ['colId' => 'responsible_name', 'hide' => true, 'width' => 180, 'order' => 2],
            ],
        ]);

        $response->assertRedirect(route('settings.tables.index'));

        $role = DB::table('roles')->where('id', $managerRoleId)->first();
        $columnsConfig = json_decode($role->columns_config, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['hide' => false, 'colId' => 'number', 'order' => 0, 'width' => 110],
            ['hide' => false, 'colId' => 'title', 'order' => 1, 'width' => 240],
            ['hide' => true, 'colId' => 'responsible_name', 'order' => 2, 'width' => 180],
        ], $columnsConfig['leads']);
    }

    public function test_admin_can_update_role_contractor_table_preset(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.tables.update', $managerRoleId), [
            'table' => 'contractors',
            'columns' => [
                ['colId' => 'name', 'hide' => false, 'width' => 220, 'order' => 0],
                ['colId' => 'status_text', 'hide' => false, 'width' => 130, 'order' => 1],
                ['colId' => 'primary_contact', 'hide' => true, 'width' => 180, 'order' => 2],
            ],
        ]);

        $response->assertRedirect(route('settings.tables.index'));

        $role = DB::table('roles')->where('id', $managerRoleId)->first();
        $columnsConfig = json_decode($role->columns_config, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['hide' => false, 'colId' => 'name', 'order' => 0, 'width' => 220],
            ['hide' => false, 'colId' => 'status_text', 'order' => 1, 'width' => 130],
            ['hide' => true, 'colId' => 'primary_contact', 'order' => 2, 'width' => 180],
        ], $columnsConfig['contractors']);
    }

    public function test_admin_can_update_role_payment_schedule_table_preset(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.tables.update', $managerRoleId), [
            'table' => 'payment_schedule',
            'columns' => [
                ['colId' => 'order_number', 'hide' => false, 'width' => 160, 'order' => 0],
                ['colId' => 'counterparty_name', 'hide' => false, 'width' => 200, 'order' => 1],
                ['colId' => 'amount', 'hide' => true, 'width' => 130, 'order' => 2],
            ],
        ]);

        $response->assertRedirect(route('settings.tables.index'));

        $role = DB::table('roles')->where('id', $managerRoleId)->first();
        $columnsConfig = json_decode($role->columns_config, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['hide' => false, 'colId' => 'order_number', 'order' => 0, 'width' => 160],
            ['hide' => false, 'colId' => 'counterparty_name', 'order' => 1, 'width' => 200],
            ['hide' => true, 'colId' => 'amount', 'order' => 2, 'width' => 130],
        ], $columnsConfig['payment_schedule']);
    }

    public function test_admin_can_open_motivation_hub_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.motivation.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Motivation')
            ->has('sections', 2)
            ->where('sections.0.href', route('settings.motivation.kpi'))
            ->where('sections.1.href', route('settings.motivation.salary'))
        );
    }

    public function test_admin_can_open_kpi_settings_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.motivation.kpi'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Kpi')
            ->has('deductionRules')
            ->where('bonusMultiplier', 1.3)
            ->where('insuranceMultiplier', 1.2)
        );
    }

    public function test_admin_can_open_salary_conditions_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.motivation.salary'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/MotivationSalary')
            ->has('employees')
            ->has('salaryCoefficients')
        );
    }

    public function test_admin_can_update_kpi_multipliers(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.motivation.kpi.update'), [
            'bonus_multiplier' => 1.45,
            'insurance_multiplier' => 1.25,
        ]);

        $response->assertRedirect(route('settings.motivation.kpi'));

        $this->assertDatabaseHas('kpi_settings', [
            'key' => 'delta_bonus_multiplier',
            'value' => '1.45',
        ]);

        $this->assertDatabaseHas('kpi_settings', [
            'key' => 'delta_insurance_multiplier',
            'value' => '1.25',
        ]);
    }

    public function test_user_with_only_settings_motivation_can_access_motivation_routes(): void
    {
        $roleId = $this->createRoleWithAreas('motivation_editor', 'Мотивация', ['dashboard', 'settings_motivation']);
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get(route('settings.motivation.kpi'))->assertOk();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.users.index'))->assertForbidden();
    }

    public function test_user_with_only_settings_motivation_sees_only_motivation_section_on_hub(): void
    {
        $roleId = $this->createRoleWithAreas('motivation_editor2', 'Мотивация 2', ['dashboard', 'settings_motivation']);
        $user = User::factory()->create(['role_id' => $roleId]);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('sections', 1)
            ->where('sections.0.key', 'motivation')
        );
    }

    public function test_legacy_settings_visibility_area_grants_system_and_motivation_routes(): void
    {
        $roleId = $this->createRoleWithAreas('legacy_settings', 'Legacy', ['dashboard', 'settings']);
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get(route('settings.users.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.motivation.kpi'))->assertOk();
    }

    public function test_granular_settings_override_legacy_settings_area(): void
    {
        $roleId = $this->createRoleWithAreas('mixed_settings', 'Mixed', ['dashboard', 'settings_motivation']);
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get(route('settings.motivation.kpi'))->assertOk();
        $this->actingAs($user)->get(route('settings.users.index'))->assertForbidden();
    }

    public function test_admin_can_create_salary_coefficient_for_employee(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $manager = User::factory()->create(['role_id' => $managerRoleId]);

        $response = $this->actingAs($admin)->post(route('settings.motivation.salary.store'), [
            'manager_id' => $manager->id,
            'base_salary' => 70000,
            'bonus_percent' => 12,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('settings.motivation.salary'));

        $this->assertDatabaseHas('salary_coefficients', [
            'manager_id' => $manager->id,
            'base_salary' => 70000,
            'bonus_percent' => 12,
            'effective_from' => '2026-04-01 00:00:00',
            'is_active' => 1,
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

    /**
     * @param  list<string>  $visibilityAreas
     */
    private function createRoleWithAreas(string $name, string $displayName, array $visibilityAreas): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'visibility_areas' => json_encode($visibilityAreas, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
