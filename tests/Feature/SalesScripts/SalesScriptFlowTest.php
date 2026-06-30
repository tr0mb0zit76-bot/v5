<?php

namespace Tests\Feature\SalesScripts;

use App\Models\Lead;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesScriptFlowTest extends TestCase
{
    public function test_guest_is_redirected_from_scripts_index(): void
    {
        $this->get(route('scripts.index'))->assertRedirect();
    }

    public function test_user_without_scripts_area_cannot_access_scripts_index(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'no_scripts',
            'display_name' => 'No scripts',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('scripts.index'))->assertForbidden();
    }

    public function test_scripts_index_includes_graph_shortcut_for_script_managers(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'scripts_manager',
            'display_name' => 'Scripts manager',
            'visibility_areas' => json_encode(['dashboard', 'scripts', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $versionId = (int) SalesScriptVersion::query()->orderByDesc('updated_at')->value('id');

        $this->actingAs($user)
            ->get(route('scripts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesScripts/Index')
                ->where('latestGraphVersionId', $versionId)
                ->has('scripts.0.latest_editor_version.id')
            );
    }

    public function test_manager_can_run_demo_script_and_complete_session(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager_scripts',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $versionId = (int) SalesScriptVersion::query()->value('id');
        $positiveId = (int) SalesScriptReactionClass::query()->where('key', 'positive_signal')->value('id');

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $versionId,
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame($user->id, $session->user_id);

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => $positiveId,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $session->refresh();
        $this->assertSame('end', $session->currentNode?->client_key);

        $this->actingAs($user)
            ->post(route('scripts.sessions.complete', $session), [
                'outcome' => 'progress',
                'primary_reaction_class_id' => null,
                'notes' => 'Тестовая сессия',
            ])
            ->assertRedirect(route('scripts.index'));

        $session->refresh();
        $this->assertNotNull($session->completed_at);
        $this->assertSame('progress', $session->outcome->value);
        $this->assertGreaterThanOrEqual(6, $session->events()->count());
    }

    public function test_demo_scripts_are_published_working_instructions(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $fieldCodes = SalesScriptCaptureField::query()
            ->pluck('code')
            ->all();

        $nodes = SalesScriptNode::query()
            ->whereHas('version', fn ($query) => $query->where('is_active', true)->whereNotNull('published_at'))
            ->get();

        $scriptTitles = SalesScript::query()->pluck('title')->all();

        $this->assertGreaterThanOrEqual(90, $nodes->count());
        $this->assertSame(13, SalesScriptVersion::query()->where('is_active', true)->whereNotNull('published_at')->count());
        $this->assertContains('Дожим КП после отправки', $scriptTitles);
        $this->assertContains('Тендер / закупщик', $scriptTitles);
        $this->assertContains('Возврат уснувшего лида', $scriptTitles);
        $this->assertContains('Переговоры по цене и марже', $scriptTitles);
        $this->assertContains('Проблемный рейс / удержание клиента', $scriptTitles);
        $this->assertContains('Повторная продажа действующему клиенту', $scriptTitles);
        $this->assertContains('Тренажёр: цена и конкурент', $scriptTitles);
        $this->assertContains('Тренажёр: конфликт и удержание', $scriptTitles);

        foreach ([
            'target_rate',
            'decision_maker',
            'required_documents',
            'claim_reason',
            'service_recovery_plan',
            'own_fleet_argument',
        ] as $expectedFieldCode) {
            $this->assertContains($expectedFieldCode, $fieldCodes);
        }

        foreach ($nodes as $node) {
            $body = (string) $node->body;

            $this->assertDoesNotMatchRegularExpression('/\[[^\]]+\]/u', $body, 'Seed script still contains draft placeholders.');

            preg_match_all('/\{([a-z0-9_]+)\}/u', $body, $matches);

            foreach ($matches[1] as $placeholder) {
                $this->assertContains($placeholder, $fieldCodes, "Missing capture field for {{$placeholder}}.");
            }
        }

        $instructionNodes = $nodes->filter(fn (SalesScriptNode $node): bool => str_contains((string) $node->body, 'Цель шага')
            || str_contains((string) $node->body, 'После разговора')
            || str_contains((string) $node->body, 'Тренировка'));

        $this->assertGreaterThanOrEqual(40, $instructionNodes->count());

        $trainerScripts = SalesScript::query()
            ->where('title', 'like', 'Тренажёр:%')
            ->count();

        $this->assertGreaterThanOrEqual(3, $trainerScripts);

        $transitionsWithoutClientReply = SalesScriptTransition::query()
            ->whereHas('version', fn ($query) => $query->where('is_active', true)->whereNotNull('published_at'))
            ->where(fn ($query) => $query
                ->whereNull('customer_label')
                ->orWhere('customer_label', '')
            )
            ->count();

        $this->assertSame(0, $transitionsWithoutClientReply, 'Every seeded transition must have a client-facing reply label.');
    }

    public function test_completion_creates_crm_next_step_from_captured_date(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager_scripts_crm_actions',
            'display_name' => 'Manager scripts CRM actions',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $scriptId = (int) SalesScript::query()->where('title', 'Возврат уснувшего лида')->value('id');
        $version = SalesScriptVersion::query()
            ->where('sales_script_id', $scriptId)
            ->where('is_active', true)
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $version->id,
                'lead_id' => $lead->id,
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->firstOrFail();
        $nextStepDate = now()->addDays(3)->toDateString();

        $this->actingAs($user)
            ->post(route('scripts.sessions.advance', $session), [
                'sales_script_reaction_class_id' => null,
                'field_values' => [
                    'next_step_date' => $nextStepDate,
                ],
            ])
            ->assertRedirect(route('scripts.sessions.show', $session));

        $this->actingAs($user)
            ->post(route('scripts.sessions.complete', $session), [
                'outcome' => 'progress',
                'notes' => 'Клиент просит вернуться позже',
                'lead_id' => $lead->id,
            ])
            ->assertRedirect(route('scripts.index'));

        $session->refresh();
        $lead->refresh();

        $this->assertSame($lead->id, $session->lead_id);
        $this->assertNotNull($lead->next_contact_at);
        $this->assertSame($nextStepDate, $lead->next_contact_at->toDateString());

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'note',
            'subject' => 'Итог прохождения скрипта',
        ]);

        $task = Task::query()->where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertSame($nextStepDate, $task->due_at?->toDateString());
        $this->assertSame('high', $task->priority);
        $this->assertSame($session->id, $task->meta['sales_script_play_session_id'] ?? null);
    }

    public function test_trainer_can_start_session_with_manager_as_buyer(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager_trainer',
            'display_name' => 'Manager Trainer',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $versionId = (int) SalesScriptVersion::query()->value('id');

        $this->actingAs($user)
            ->post(route('scripts.sessions.store'), [
                'sales_script_version_id' => $versionId,
                'return_to' => 'trainer',
                'trainer_profile_key' => 'procurement-formal',
                'trainer_profile_title' => 'Закупщик',
                'trainer_profile_context' => 'Покупатель требует факты и KPI.',
                'training_role_mode' => 'manager_buyer',
            ])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame('manager_buyer', $session->training_role_mode);

        $this->actingAs($user)
            ->get(route('scripts.sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesScripts/Play')
                ->where('playContext.return', 'trainer')
                ->where('playContext.training_role_mode', 'manager_buyer')
            );
    }

    public function test_user_cannot_advance_foreign_session(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'm1',
            'display_name' => 'M1',
            'visibility_areas' => json_encode(['scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = User::factory()->create(['role_id' => $roleId, 'email_verified_at' => now()]);
        $other = User::factory()->create(['role_id' => $roleId, 'email_verified_at' => now()]);

        $versionId = (int) SalesScriptVersion::query()->value('id');

        $this->actingAs($owner)
            ->post(route('scripts.sessions.store'), ['sales_script_version_id' => $versionId])
            ->assertRedirect();

        $session = SalesScriptPlaySession::query()->first();
        $this->assertNotNull($session);

        $this->actingAs($other)
            ->post(route('scripts.sessions.advance', $session), ['sales_script_reaction_class_id' => null])
            ->assertForbidden();
    }
}
