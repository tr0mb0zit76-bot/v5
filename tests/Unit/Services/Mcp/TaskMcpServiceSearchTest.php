<?php

namespace Tests\Unit\Services\Mcp;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Mcp\TaskMcpService;
use Tests\TestCase;

class TaskMcpServiceSearchTest extends TestCase
{
    public function test_search_matches_responsible_name_fragment(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => ['tasks'],
        ]);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $responsible = User::factory()->create(['name' => 'Тищенко Дина Владимировна']);
        $other = User::factory()->create(['name' => 'Иванов Петр']);

        Task::query()->create([
            'number' => 'T-1001',
            'title' => 'Позвонить клиенту',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $responsible->id,
            'created_by' => $admin->id,
        ]);

        Task::query()->create([
            'number' => 'T-1002',
            'title' => 'Другая задача',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $other->id,
            'created_by' => $admin->id,
        ]);

        $result = app(TaskMcpService::class)->search($admin, 'Тищенко', 25);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Тищенко Дина Владимировна', $result['tasks'][0]['responsible_name']);
        $this->assertSame('Позвонить клиенту', $result['tasks'][0]['title']);
    }
}
