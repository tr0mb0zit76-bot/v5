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
    }

    public function test_admin_can_open_settings_hub(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('sections', 4)
        );
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

    public function test_admin_can_open_kpi_settings_page(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $response = $this->actingAs($admin)->get(route('settings.motivation.kpi'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Kpi')
            ->has('thresholdPreview', 3)
            ->where('thresholdPreview.0.direct', 3)
        );
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
}
