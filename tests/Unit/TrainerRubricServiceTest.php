<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlayEvent;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptPlaySessionFieldValue;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\TrainerRubricService;
use App\Services\SalesScripts\TrainerScoreCalculator;
use Tests\TestCase;

class TrainerRubricServiceTest extends TestCase
{
    public function test_resolves_price_and_conflict_rubrics_from_script_context(): void
    {
        $service = new TrainerRubricService;

        $priceSession = $this->sessionForScript('Тренажёр: цена и конкурент', ['тренажёр', 'цена', 'конкурент']);
        $conflictSession = $this->sessionForScript('Проблемный рейс / удержание клиента', ['претензия', 'удержание']);

        $this->assertSame('price', $service->forSession($priceSession)['key']);
        $this->assertSame('conflict', $service->forSession($conflictSession)['key']);
    }

    public function test_evaluates_discovery_rubric_from_session_data(): void
    {
        $service = new TrainerRubricService;
        $session = $this->sessionForScript('Холодный звонок', ['знакомство']);
        $version = $session->version;

        $intro = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'intro',
            'kind' => SalesScriptNodeKind::Ask,
            'body' => 'Спросить вводные',
            'sort_order' => 10,
        ]);
        $qualify = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'qualify',
            'kind' => SalesScriptNodeKind::Ask,
            'body' => 'Собрать маршрут и критерии',
            'sort_order' => 20,
        ]);

        foreach ([$intro, $qualify] as $node) {
            SalesScriptPlayEvent::query()->create([
                'sales_script_play_session_id' => $session->id,
                'type' => SalesPlayEventType::EnteredNode,
                'sales_script_node_id' => $node->id,
                'created_at' => now(),
            ]);
        }

        $this->capture($session, 'route_from', 'Смоленск', $qualify);
        $this->capture($session, 'cargo_type', 'Автозапчасти', $qualify);
        $this->capture($session, 'decision_criteria', 'Срок подачи и документы', $qualify);
        $this->capture($session, 'next_step_date', '2026-07-01', $qualify);

        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Добрый день, подскажите по перевозкам.',
        ]);

        $rubric = $service->forSession($session->fresh());

        $this->assertSame('discovery', $rubric['key']);
        $this->assertSame(4, $rubric['passed_count']);
        $this->assertSame(4, $rubric['total_count']);
        $this->assertSame(100, $rubric['rubric_score']);
        $this->assertSame('passed', $rubric['evaluated_criteria'][0]['status']);
    }

    public function test_trainer_score_uses_rubric_completion(): void
    {
        $session = $this->sessionForScript('Холодный звонок', ['знакомство']);
        $session->update(['completed_at' => now()]);
        $version = $session->version;
        $node = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'intro',
            'kind' => SalesScriptNodeKind::Ask,
            'body' => 'Спросить вводные',
            'sort_order' => 10,
        ]);
        $nextNode = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'next_step',
            'kind' => SalesScriptNodeKind::Say,
            'body' => 'Зафиксировать следующий шаг',
            'sort_order' => 20,
        ]);

        $this->capture($session, 'route_from', 'Смоленск', $node);
        $this->capture($session, 'cargo_type', 'Автозапчасти', $node);
        $this->capture($session, 'decision_criteria', 'Срок подачи и документы', $node);
        $this->capture($session, 'next_step_date', '2026-07-01', $node);

        SalesScriptPlayEvent::query()->create([
            'sales_script_play_session_id' => $session->id,
            'type' => SalesPlayEventType::EnteredNode,
            'sales_script_node_id' => $node->id,
            'created_at' => now(),
        ]);
        SalesScriptPlayEvent::query()->create([
            'sales_script_play_session_id' => $session->id,
            'type' => SalesPlayEventType::EnteredNode,
            'sales_script_node_id' => $nextNode->id,
            'created_at' => now(),
        ]);
        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Добрый день.',
        ]);
        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Здравствуйте.',
        ]);
        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Маршрут Смоленск — Москва, груз автозапчасти.',
        ]);
        SalesScriptTrainerMessage::query()->create([
            'sales_script_play_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Фиксирую следующий шаг.',
        ]);

        $score = (new TrainerScoreCalculator(new TrainerRubricService))
            ->calculate($session->fresh(), SalesPlaySessionOutcome::Progress);

        $this->assertGreaterThan(70, $score);
    }

    /**
     * @param  list<string>  $tags
     */
    private function sessionForScript(string $title, array $tags): SalesScriptPlaySession
    {
        $script = SalesScript::query()->create([
            'title' => $title,
            'description' => null,
            'channel' => 'phone',
            'tags' => $tags,
        ]);

        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => now(),
            'is_active' => true,
            'entry_node_key' => 'intro',
        ]);

        return SalesScriptPlaySession::query()->create([
            'user_id' => User::factory()->create()->id,
            'sales_script_version_id' => $version->id,
            'is_trainer' => true,
            'started_at' => now(),
        ]);
    }

    private function capture(SalesScriptPlaySession $session, string $code, string $value, SalesScriptNode $node): void
    {
        $field = SalesScriptCaptureField::query()->firstOrCreate(
            ['code' => $code],
            ['label' => $code, 'value_type' => 'text'],
        );

        SalesScriptPlaySessionFieldValue::query()->create([
            'sales_script_play_session_id' => $session->id,
            'sales_script_capture_field_id' => $field->id,
            'value' => $value,
            'captured_at_node_id' => $node->id,
        ]);
    }
}
