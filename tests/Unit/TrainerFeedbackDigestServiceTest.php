<?php

namespace Tests\Unit;

use App\Enums\SalesScriptNodeKind;
use App\Enums\SalesTrainerDialogQuality;
use App\Models\Role;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\TrainerFeedbackDigestService;
use Tests\TestCase;

class TrainerFeedbackDigestServiceTest extends TestCase
{
    public function test_digest_surfaces_training_feedback_for_human_script_edits(): void
    {
        $role = Role::query()->create([
            'name' => 'trainer_feedback_admin',
            'display_name' => 'Trainer Feedback Admin',
            'visibility_areas' => ['sales_assistant_trainer_analytics'],
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $script = SalesScript::query()->create([
            'title' => 'Тестовый тренажёр',
            'description' => 'Цена и конкурент',
            'channel' => 'phone',
            'tags' => ['тренажёр', 'цена'],
        ]);
        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => now(),
            'is_active' => true,
            'entry_node_key' => 'intro',
        ]);
        $introNode = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'intro',
            'kind' => SalesScriptNodeKind::Ask,
            'body' => 'Спросить критерии выбора — {decision_criteria}.',
            'hint' => 'Сначала критерии.',
            'capture_field_codes' => ['decision_criteria'],
            'sort_order' => 10,
        ]);
        SalesScriptCaptureField::query()->create([
            'code' => 'decision_criteria',
            'label' => 'Критерии выбора клиента',
            'value_type' => 'text',
        ]);
        $reaction = SalesScriptReactionClass::query()->create([
            'key' => 'price_objection',
            'label' => 'Возражение по цене',
            'sort_order' => 10,
        ]);

        $trainerSession = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'is_trainer' => true,
            'trainer_profile_key' => 'hard-price-negotiator',
            'trainer_profile_title' => 'Жёсткий переговорщик',
            'trainer_dialog_quality' => SalesTrainerDialogQuality::Stuck->value,
            'trainer_score' => 40,
            'started_at' => now(),
        ]);
        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $trainerSession->id,
            'sales_script_node_id' => $introNode->id,
            'step_key' => 'intro',
            'role' => 'assistant',
            'content' => 'Дайте скидку, пожалуйста.',
            'peer_reaction' => 'negative',
            'feedback_tags' => ['bad_missed_objection', 'bad_wrong_stage'],
        ]);

        SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'is_trainer' => false,
            'primary_reaction_class_id' => $reaction->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $digest = app(TrainerFeedbackDigestService::class)->digest(
            user: $user,
            days: 30,
            canViewAll: true,
            versionId: $version->id,
        );

        $this->assertTrue($digest['available']);
        $this->assertSame(1, $digest['summary']['total_sessions']);
        $this->assertSame(1, $digest['summary']['negative_messages']);
        $this->assertSame(1, $digest['summary']['stuck_or_failure_sessions']);
        $this->assertSame('Тестовый тренажёр · v1', $digest['script_hotspots'][0]['script_label']);
        $this->assertSame('intro', $digest['node_hotspots'][0]['step_key']);
        $this->assertSame('bad_missed_objection', $digest['node_hotspots'][0]['top_tags'][0]['tag']);
        $this->assertSame('мимо возражения', $digest['feedback_tag_hotspots'][0]['label']);
        $this->assertSame('Жёсткий переговорщик', $digest['profile_hotspots'][0]['profile_title']);
        $this->assertSame('Возражение по цене', $digest['live_objections'][0]['label']);
        $this->assertSame('decision_criteria', $digest['missing_fields'][0]['code']);
        $this->assertNotEmpty($digest['recommendations']);
    }
}
