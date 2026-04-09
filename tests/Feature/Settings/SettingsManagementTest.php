<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('salary_coefficients');
        Schema::dropIfExists('kpi_thresholds');
        Schema::dropIfExists('kpi_settings');
        Schema::dropIfExists('contractor_activity_types');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('site_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('theme', 20)->default('light');
            $table->boolean('is_active')->default(true);
            $table->json('ai_preferences')->nullable();
            $table->boolean('ai_learning_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('kpi_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('deal_type', 50);
            $table->decimal('threshold_from', 5, 2);
            $table->decimal('threshold_to', 5, 2);
            $table->integer('kpi_percent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_coefficients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id');
            $table->integer('base_salary')->default(0);
            $table->integer('bonus_percent')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractor_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function test_admin_can_open_settings_hub(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('sections', 6)
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
            ->has('dictionaries', 1)
            ->where('dictionaries.0.key', 'contractor-activity-types')
            ->where('dictionaries.0.items.0.name', 'Экспедирование')
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
            ->where('orderColumns.0.field', 'id')
        );
    }

    public function test_admin_can_update_role_order_table_preset(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $managerRoleId = $this->createRole('manager', 'Менеджер');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.tables.update', $managerRoleId), [
            'orders' => [
                ['colId' => 'order_number', 'hide' => false, 'width' => 100, 'order' => 0],
                ['colId' => 'manager_name', 'hide' => false, 'width' => 160, 'order' => 1],
                ['colId' => 'salary_paid', 'hide' => true, 'width' => 120, 'order' => 2],
            ],
        ]);

        $response->assertRedirect(route('settings.tables.index'));

        $role = DB::table('roles')->where('id', $managerRoleId)->first();
        $columnsConfig = json_decode($role->columns_config, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['colId' => 'order_number', 'hide' => false, 'width' => 100, 'order' => 0],
            ['colId' => 'manager_name', 'hide' => false, 'width' => 160, 'order' => 1],
            ['colId' => 'salary_paid', 'hide' => true, 'width' => 120, 'order' => 2],
        ], $columnsConfig['orders']);
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
            ->has('thresholds', 3)
            ->where('thresholds.0.direct_kpi', 3)
            ->where('bonusMultiplier', 1.3)
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

    public function test_admin_can_update_kpi_thresholds_and_bonus_multiplier(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->patch(route('settings.motivation.kpi.update'), [
            'bonus_multiplier' => 1.45,
            'thresholds' => [
                [
                    'threshold_from' => 0.00,
                    'threshold_to' => 0.49,
                    'direct_kpi' => 4,
                    'indirect_kpi' => 8,
                ],
                [
                    'threshold_from' => 0.50,
                    'threshold_to' => 1.00,
                    'direct_kpi' => 5,
                    'indirect_kpi' => 9,
                ],
            ],
        ]);

        $response->assertRedirect(route('settings.motivation.kpi'));

        $this->assertDatabaseHas('kpi_settings', [
            'key' => 'delta_bonus_multiplier',
            'value' => '1.45',
        ]);

        $this->assertDatabaseHas('kpi_thresholds', [
            'deal_type' => 'direct',
            'threshold_from' => '0.00',
            'threshold_to' => '0.49',
            'kpi_percent' => 4,
        ]);

        $this->assertDatabaseHas('kpi_thresholds', [
            'deal_type' => 'indirect',
            'threshold_from' => '0.50',
            'threshold_to' => '1.00',
            'kpi_percent' => 9,
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
        $roleId = $this->createRoleWithAreas('mixed_settings', 'Mixed', ['dashboard', 'settings', 'settings_motivation']);
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
