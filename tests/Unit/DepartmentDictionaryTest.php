<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepartmentDictionaryTest extends TestCase
{
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

        $this->assertContains('Логистика', $activeNames);
        $this->assertNotContains('Архив', $activeNames);
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
