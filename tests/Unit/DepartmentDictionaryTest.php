<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepartmentDictionaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('department_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_approvals')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'department_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function it_lists_only_active_departments_for_user_assignment(): void
    {
        Department::query()->create(['name' => 'Логистика', 'sort_order' => 10, 'is_active' => true]);
        Department::query()->create(['name' => 'Архив', 'sort_order' => 20, 'is_active' => false]);

        $activeNames = Department::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();

        $this->assertSame(['Логистика'], $activeNames);
    }

    #[Test]
    public function it_knows_when_department_has_linked_users(): void
    {
        $department = Department::query()->create(['name' => 'Продажи', 'sort_order' => 10, 'is_active' => true]);
        $user = User::query()->create([
            'name' => 'Менеджер',
            'email' => 'manager@example.com',
            'password' => 'secret',
        ]);

        $department->users()->attach($user->id, [
            'is_primary' => true,
            'receives_approvals' => false,
        ]);

        $this->assertTrue($department->users()->exists());
        $this->assertSame(1, $department->users()->count());
    }
}
