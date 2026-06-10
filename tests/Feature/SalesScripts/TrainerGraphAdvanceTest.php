<?php

namespace Tests\Feature\SalesScripts;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\TrainerClientReactionMatcher;
use App\Services\SalesScripts\TrainerGraphCoordinatorService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrainerGraphAdvanceTest extends TestCase
{
    use RefreshDatabase;

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
}
