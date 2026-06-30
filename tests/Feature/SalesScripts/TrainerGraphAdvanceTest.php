<?php

namespace Tests\Feature\SalesScripts;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptPlaySessionFieldValue;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\TrainerClientReactionMatcher;
use App\Services\SalesScripts\TrainerGraphCoordinatorService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrainerGraphAdvanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
            });
        }

        $this->app->bind(ChatCompletionClient::class, fn (): ChatCompletionClient => new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return 'Да, это я. Соединяю с ЛПР, слушаю вас.';
            }
        });
    }

    public function test_trainer_message_advances_graph_from_intro_and_returns_presentation(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $user = $this->scriptsUser();
        $version = $this->coldCallVersion();
        $introNode = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'intro')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $version->id,
                'return_to' => 'trainer',
                'trainer_profile_key' => 'lpr-skeptic',
                'trainer_profile_title' => 'ЛПР: скептик',
                'trainer_profile_context' => 'Скептичен к смене перевозчика.',
                'training_role_mode' => 'manager_seller',
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->firstOrFail();
        $this->assertSame((int) $introNode->id, (int) $session->current_node_id);

        $response = $this->actingAs($user)
            ->postJson(route('scripts.sessions.trainer-message', $session), [
                'message' => 'Добрый день! Подскажите, кто у вас курирует перевозки?',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'reply',
                'history',
                'play_presentation' => ['operator_kind', 'step_key', 'choices'],
                'trainer_step_hints',
                'event_trail',
                'current_node',
            ]);

        $session->refresh();
        $lprOpen = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'lpr_open')
            ->firstOrFail();

        $this->assertNotSame((int) $introNode->id, (int) $session->current_node_id);
        $this->assertSame((int) $lprOpen->id, (int) $session->current_node_id);
        $this->assertSame('lpr_open', $response->json('play_presentation.step_key'));
    }

    public function test_trainer_message_peer_reaction_stores_node_context_and_feedback_tags(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $user = $this->scriptsUser();
        $version = $this->coldCallVersion();
        $introNode = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'intro')
            ->firstOrFail();

        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'current_node_id' => $introNode->id,
            'is_trainer' => true,
            'training_role_mode' => 'manager_seller',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('scripts.sessions.trainer-message', $session), [
                'message' => 'Добрый день! Кто у вас отвечает за ЭТРН?',
            ])
            ->assertOk();

        $managerMessage = SalesScriptTrainerMessage::query()
            ->where('sales_script_play_session_id', $session->id)
            ->where('role', 'user')
            ->firstOrFail();
        $assistantMessage = SalesScriptTrainerMessage::query()
            ->where('sales_script_play_session_id', $session->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($introNode->id, $managerMessage->sales_script_node_id);
        $this->assertSame('intro', $managerMessage->step_key);
        $this->assertNotNull($assistantMessage->sales_script_node_id);
        $this->assertNotNull($assistantMessage->step_key);

        $this->actingAs($user)
            ->patchJson(route('scripts.sessions.trainer-message.peer-reaction', [
                'sales_script_play_session' => $session,
                'trainer_message' => $assistantMessage,
            ]), [
                'peer_reaction' => 'negative',
                'feedback_tags' => ['bad_missed_objection', 'bad_wrong_stage'],
            ])
            ->assertOk()
            ->assertJsonPath('peer_reaction', 'negative')
            ->assertJsonPath('feedback_tags.0', 'bad_missed_objection');

        $assistantMessage->refresh();

        $this->assertSame(['bad_missed_objection', 'bad_wrong_stage'], $assistantMessage->feedback_tags);
    }

    public function test_graph_coordinator_advances_linear_then_matches_client_reaction(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $user = $this->scriptsUser();
        $version = $this->coldCallVersion();
        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'current_node_id' => SalesScriptNode::query()
                ->where('sales_script_version_id', $version->id)
                ->where('client_key', 'intro')
                ->value('id'),
            'is_trainer' => true,
            'training_role_mode' => 'manager_seller',
            'started_at' => now(),
        ]);

        $coordinator = $this->app->make(TrainerGraphCoordinatorService::class);
        $matcher = $this->app->make(TrainerClientReactionMatcher::class);

        $this->assertTrue($coordinator->afterManagerMessage($session->fresh(['currentNode'])));
        $session->refresh();

        $gatekeeper = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'gatekeeper_branch')
            ->firstOrFail();
        $this->assertSame((int) $gatekeeper->id, (int) $session->current_node_id);

        $match = $matcher->match($gatekeeper, 'Не сейчас, напишите на почту');
        $this->assertNotNull($match);
        $this->assertTrue($coordinator->afterClientReply($session->fresh(['currentNode']), 'Не сейчас, напишите на почту'));

        $session->refresh();
        $clarify = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'clarify_contact')
            ->firstOrFail();
        $this->assertSame((int) $clarify->id, (int) $session->current_node_id);
    }

    public function test_graph_waits_for_client_reply_on_ask_node_before_linear_advance(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $user = $this->scriptsUser();
        $version = $this->coldCallVersion();
        $askNode = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'clarify_contact')
            ->firstOrFail();

        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'current_node_id' => $askNode->id,
            'is_trainer' => true,
            'training_role_mode' => 'manager_seller',
            'started_at' => now(),
        ]);

        $coordinator = $this->app->make(TrainerGraphCoordinatorService::class);

        $this->assertFalse($coordinator->afterManagerMessage($session->fresh(['currentNode'])));
        $session->refresh();
        $this->assertSame((int) $askNode->id, (int) $session->current_node_id);

        $this->assertTrue($coordinator->afterClientReply(
            $session->fresh(['currentNode']),
            'Меня зовут Андрей. Я директор по операциям и отвечаю за документооборот.',
        ));

        $session->refresh();
        $this->assertNotSame((int) $askNode->id, (int) $session->current_node_id);
    }

    public function test_skeptic_reply_moves_to_contact_clarification_branch(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $version = $this->coldCallVersion();
        $gatekeeper = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'gatekeeper_branch')
            ->firstOrFail();

        $matcher = $this->app->make(TrainerClientReactionMatcher::class);

        $match = $matcher->match(
            $gatekeeper,
            'У нас всё работает в штатном режиме, перевозчик нас устраивает. Если есть конкретное предложение — напишите на почту.',
        );

        $this->assertNotNull($match);

        $transition = $gatekeeper->outgoingTransitions()
            ->with(['reactionClass', 'toNode'])
            ->where('sales_script_reaction_class_id', $match['reaction_class_id'])
            ->firstOrFail();

        $this->assertContains($transition->reactionClass?->key, ['need_info', 'stall']);
        $this->assertSame('clarify_contact', $transition->toNode?->client_key);
    }

    public function test_trainer_message_saves_current_step_field_values_and_returns_rubric(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $user = $this->scriptsUser();
        $version = $this->meetingVersion();
        $priceNode = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'objection_price')
            ->firstOrFail();

        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $version->id,
            'current_node_id' => $priceNode->id,
            'is_trainer' => true,
            'trainer_profile_key' => 'hard-price-negotiator',
            'trainer_profile_title' => 'Жёсткий переговорщик по цене',
            'training_role_mode' => 'manager_seller',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('scripts.sessions.trainer-message', $session), [
                'message' => 'Сравним не только цену, но и условия.',
                'field_values' => [
                    'budget_window' => 'Конкурент дешевле на 15%',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('trainer_rubric.key', 'price');

        $field = SalesScriptCaptureField::query()->where('code', 'budget_window')->firstOrFail();

        $this->assertSame(
            'Конкурент дешевле на 15%',
            SalesScriptPlaySessionFieldValue::query()
                ->where('sales_script_play_session_id', $session->id)
                ->where('sales_script_capture_field_id', $field->id)
                ->value('value'),
        );
    }

    private function scriptsUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager_trainer_graph',
            'display_name' => 'Manager Trainer Graph',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);
    }

    private function coldCallVersion(): SalesScriptVersion
    {
        $scriptId = SalesScript::query()->where('title', 'Холодный звонок')->value('id');
        $this->assertNotNull($scriptId);

        return SalesScriptVersion::query()
            ->where('sales_script_id', $scriptId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function meetingVersion(): SalesScriptVersion
    {
        $scriptId = SalesScript::query()->where('title', 'Знакомство')->value('id');
        $this->assertNotNull($scriptId);

        return SalesScriptVersion::query()
            ->where('sales_script_id', $scriptId)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
