<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use App\Support\AiInteractionOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsAiAnalyticsSalesBookGapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ai_interaction_events')) {
            $this->markTestSkipped('ai_interaction_events table is missing.');
        }
    }

    public function test_settings_system_user_can_dismiss_sales_book_gap(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'ai_analytics',
            'display_name' => 'AI analytics',
            'visibility_areas' => json_encode(['dashboard', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $eventId = DB::table('ai_interaction_events')->insertGetId([
            'user_id' => $user->id,
            'feature' => AiInteractionFeature::CommandBar->value,
            'event_type' => AiInteractionEventType::ConversationTurn->value,
            'channel' => 'openai',
            'outcome' => AiInteractionOutcome::WeakAnswer->value,
            'ok' => true,
            'user_prompt_redacted' => 'Как оформить международку?',
            'metadata' => json_encode([
                'sales_book' => [
                    'gap' => true,
                    'gap_reason' => 'no_article_match',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('settings.ai-analytics.sales-book-gaps.dismiss', $eventId), ['days' => 30])
            ->assertRedirect(route('settings.ai-analytics', ['days' => 30]));

        $metadata = json_decode((string) DB::table('ai_interaction_events')->where('id', $eventId)->value('metadata'), true);

        $this->assertFalse($metadata['sales_book']['gap']);
        $this->assertTrue($metadata['sales_book']['gap_dismissed']);
        $this->assertSame($user->id, $metadata['sales_book']['gap_dismissed_by']);
    }

    public function test_dismissed_gap_is_excluded_from_analytics_insights(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'ai_analytics_2',
            'display_name' => 'AI analytics 2',
            'visibility_areas' => json_encode(['dashboard', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        DB::table('ai_interaction_events')->insert([
            'user_id' => $user->id,
            'feature' => AiInteractionFeature::CommandBar->value,
            'event_type' => AiInteractionEventType::ConversationTurn->value,
            'channel' => 'openai',
            'outcome' => AiInteractionOutcome::WeakAnswer->value,
            'ok' => true,
            'user_prompt_redacted' => 'Служебный вопрос не про книгу',
            'metadata' => json_encode([
                'sales_book' => [
                    'gap' => true,
                    'gap_dismissed' => true,
                    'gap_reason' => 'not_relevant',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('settings.ai-analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/AiAnalytics')
                ->where('insights.sales_book_knowledge_gaps', []));
    }
}
