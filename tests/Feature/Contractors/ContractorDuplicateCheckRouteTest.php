<?php

namespace Tests\Feature\Contractors;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorDuplicateCheckRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractors',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->timestamps();
        });
    }

    public function test_duplicate_check_route_is_not_shadowed_by_contractor_show(): void
    {
        $admin = $this->createAdminUser();

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Тест',
            'inn' => '2543143277',
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.duplicate-check', [
            'inn' => '2543143277',
            'name' => 'ООО Тест',
            'ignore_id' => $contractor->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('duplicate', false);
    }

    private function createAdminUser(): User
    {
        $adminRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $adminRoleId,
        ]);
    }
}
