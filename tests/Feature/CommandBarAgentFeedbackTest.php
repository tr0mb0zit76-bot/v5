<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommandBarAgentFeedbackTest extends TestCase
{
    #[Test]
    public function user_can_rate_assistant_reply(): void
    {
        if (! Schema::hasTable('ai_interaction_events')) {
            $this->markTestSkipped('ai_interaction_events table is missing.');
        }

        $user = $this->adminUser();
        $turnId = (string) Str::uuid();

        DB::table('ai_interaction_events')->insert([
            'user_id' => $user->id,
            'feature' => AiInteractionFeature::CommandBar->value,
            'event_type' => AiInteractionEventType::ConversationTurn->value,
            'channel' => 'external_large',
            'outcome' => 'success',
            'ok' => true,
            'metadata' => json_encode([
                'turn_id' => $turnId,
                'sales_book' => ['gap' => true, 'gap_reason' => 'not_read'],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('agent.command-bar.feedback'), [
            'turn_id' => $turnId,
            'rating' => 'not_helpful',
            'comment' => 'Не нашёл ответ про CMR',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('ai_interaction_events', [
            'user_id' => $user->id,
            'event_type' => AiInteractionEventType::UserFeedback->value,
        ]);
    }

    private function adminUser(): User
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => [],
            'visibility_areas' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
