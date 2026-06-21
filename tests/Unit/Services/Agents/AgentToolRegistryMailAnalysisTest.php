<?php

namespace Tests\Unit\Services\Agents;

use App\Models\Role;
use App\Models\User;
use App\Services\Agents\AgentToolRegistry;
use App\Services\Commercial\MailThreadAnalysisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentToolRegistryMailAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['users', 'roles']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    #[Test]
    public function mail_analysis_tools_are_exposed_for_mail_area(): void
    {
        $user = $this->makeUser(['mail', 'leads']);

        $toolNames = collect(app(AgentToolRegistry::class)->openAiToolsFor($user))
            ->pluck('function.name')
            ->all();

        $this->assertContains('summarize_mail_thread', $toolNames);
        $this->assertContains('draft_mail_reply', $toolNames);
        $this->assertContains('suggest_lead_next_step_from_mail', $toolNames);
    }

    #[Test]
    public function summarize_tool_delegates_to_mail_analysis_service(): void
    {
        $user = $this->makeUser(['mail']);

        $analysis = $this->createMock(MailThreadAnalysisService::class);
        $analysis->expects($this->once())
            ->method('summarizeThread')
            ->with($user, 3, 15)
            ->willReturn(['thread_id' => 3, 'summary' => 'OK']);

        $this->app->instance(MailThreadAnalysisService::class, $analysis);

        $result = app(AgentToolRegistry::class)->invoke($user, 'summarize_mail_thread', [
            'thread_id' => 3,
            'message_limit' => 15,
        ]);

        $this->assertSame(3, $result['thread_id']);
        $this->assertSame('OK', $result['summary']);
    }

    #[Test]
    public function suggest_lead_tool_requires_leads_area(): void
    {
        $mailOnly = $this->makeUser(['mail']);
        $mailAndLeads = $this->makeUser(['mail', 'leads']);

        $registry = app(AgentToolRegistry::class);

        $mailOnlyTools = collect($registry->openAiToolsFor($mailOnly))->pluck('function.name');
        $mailAndLeadsTools = collect($registry->openAiToolsFor($mailAndLeads))->pluck('function.name');

        $this->assertFalse($mailOnlyTools->contains('suggest_lead_next_step_from_mail'));
        $this->assertTrue($mailAndLeadsTools->contains('suggest_lead_next_step_from_mail'));
    }

    /**
     * @param  list<string>  $areas
     */
    private function makeUser(array $areas): User
    {
        $roleId = Role::query()->create([
            'name' => 'tool-'.uniqid(),
            'visibility_areas' => $areas,
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
