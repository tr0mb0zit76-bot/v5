<?php

namespace Tests\Unit\Services\Agents;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\Agents\AgentToolRegistry;
use Tests\TestCase;

class AgentToolRegistryLeadBriefTest extends TestCase
{
    public function test_leads_user_can_invoke_operational_brief_tool(): void
    {
        $user = $this->makeUser(['leads']);
        $lead = Lead::query()->create([
            'number' => 'LD-BRIEF-1',
            'status' => 'qualification',
            'responsible_id' => $user->id,
            'title' => 'Тест',
        ]);

        $registry = app(AgentToolRegistry::class);

        $toolNames = collect($registry->openAiToolsFor($user))->pluck('function.name');

        $this->assertTrue($toolNames->contains('get_lead_operational_brief'));

        $result = $registry->invoke($user, 'get_lead_operational_brief', [
            'lead_id' => $lead->id,
        ]);

        $this->assertArrayHasKey('brief', $result);
        $this->assertSame($lead->id, $result['brief']['lead_id']);
        $this->assertSame('stuck', $result['brief']['health']);
    }

    public function test_user_without_leads_area_cannot_invoke_operational_brief_tool(): void
    {
        $user = $this->makeUser(['orders']);
        $lead = Lead::query()->create([
            'number' => 'LD-BRIEF-2',
            'status' => 'qualification',
            'title' => 'Тест',
        ]);

        $result = app(AgentToolRegistry::class)->invoke($user, 'get_lead_operational_brief', [
            'lead_id' => $lead->id,
        ]);

        $this->assertSame('Нет доступа к инструменту get_lead_operational_brief.', $result['error']);
    }

    /**
     * @param  list<string>  $areas
     */
    private function makeUser(array $areas): User
    {
        $roleId = Role::query()->create([
            'name' => 'tool-'.uniqid(),
            'visibility_areas' => $areas,
            'visibility_scopes' => ['leads' => 'own'],
        ])->id;

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Tool User',
            'email' => 'tool-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
