<?php

namespace Tests\Unit\Support;

use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\CardSmartLinksResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CardSmartLinksResolverTest extends TestCase
{
    #[Test]
    public function it_returns_open_task_link_for_lead(): void
    {
        $role = Role::query()->create([
            'name' => 'lead_manager_links',
            'visibility_areas' => ['leads'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);

        Task::query()->create([
            'number' => 'T-1',
            'title' => 'Позвонить',
            'status' => 'new',
            'priority' => 'normal',
            'lead_id' => $lead->id,
            'responsible_id' => $user->id,
        ]);

        $links = app(CardSmartLinksResolver::class)->forLead($lead, $user);

        $this->assertNotEmpty($links);
        $this->assertSame('tasks', $links[0]['key']);
        $this->assertSame(1, $links[0]['count']);
    }
}
