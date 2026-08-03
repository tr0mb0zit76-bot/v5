<?php

namespace Tests\Feature\Improvement;

use App\Contracts\Inference\ChatCompletionClient;
use App\Enums\LeadCloseOutcomeFlag;
use App\Models\ImprovementAdoption;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\ImprovementPipelineRun;
use App\Models\ImprovementSignal;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Services\Improvement\ImprovementExperimentService;
use App\Services\Improvement\ImprovementHypothesisPipeline;
use App\Services\Improvement\ImprovementSignalCollector;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImprovementLoopTest extends TestCase
{
    /** @var list<int> */
    private array $leadIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $roleIds = [];

    /** @var list<int> */
    private array $taskIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('improvement_signals')) {
            $this->markTestSkipped('improvement tables missing — run migrations.');
        }
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('improvement_adoptions') && $this->leadIds !== []) {
            ImprovementAdoption::query()->whereIn('hypothesis_id', function ($q): void {
                $q->select('id')->from('improvement_hypotheses')->where('source', 'test_loop');
            })->delete();
        }

        if (Schema::hasTable('improvement_experiments')) {
            ImprovementExperiment::query()->where('name', 'like', 'TEST_LOOP_%')->delete();
        }

        if (Schema::hasTable('improvement_hypotheses')) {
            ImprovementHypothesis::query()->where('source', 'test_loop')->delete();
            ImprovementHypothesis::query()->where('text', 'like', 'TEST_LOOP_%')->delete();
        }

        if (Schema::hasTable('improvement_pipeline_runs')) {
            ImprovementPipelineRun::query()->where('created_at', '>=', now()->subHour())->where('status', '!=', 'running')->limit(20)->delete();
        }

        if (Schema::hasTable('improvement_signals')) {
            ImprovementSignal::query()->where('title', 'like', 'TEST_LOOP_%')->delete();
            ImprovementSignal::query()->where('kind', 'loss_flag_spike')->where('created_at', '>=', now()->subHour())->delete();
        }

        if ($this->taskIds !== [] && Schema::hasTable('tasks')) {
            Task::query()->whereIn('id', $this->taskIds)->forceDelete();
        }

        if ($this->leadIds !== []) {
            Lead::query()->whereIn('id', $this->leadIds)->forceDelete();
        }

        if ($this->userIds !== []) {
            User::query()->whereIn('id', $this->userIds)->forceDelete();
        }

        if ($this->roleIds !== []) {
            Role::query()->whereIn('id', $this->roleIds)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function collector_writes_loss_flag_signal(): void
    {
        $user = $this->makeReportsUser();

        foreach (range(1, 3) as $_) {
            $lead = Lead::factory()->create([
                'responsible_id' => $user->id,
                'status' => 'lost',
                'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostGhosting->value,
                'updated_at' => now(),
            ]);
            $this->leadIds[] = $lead->id;
        }

        $collector = new ImprovementSignalCollector(
            new ManagerDealSignalExtractor(new ActivityLedgerService),
        );

        $signals = $collector->collect(30);

        $this->assertNotEmpty($signals);
        $this->assertTrue(
            ImprovementSignal::query()->where('kind', 'loss_flag_spike')->exists()
        );
    }

    #[Test]
    public function guest_cannot_open_improvement_index(): void
    {
        $this->get(route('improvement.index'))->assertRedirect();
    }

    #[Test]
    public function reports_user_can_open_index_and_dismiss_signal(): void
    {
        $user = $this->makeReportsUser();
        $signal = ImprovementSignal::query()->create([
            'domain' => 'sales',
            'kind' => 'loss_flag_spike',
            'severity' => 'warn',
            'title' => 'TEST_LOOP_dismiss',
            'payload' => ['count' => 2],
            'status' => ImprovementSignal::STATUS_OPEN,
            'source' => 'rules',
        ]);

        $this->actingAs($user)
            ->get(route('improvement.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('improvement.signals.dismiss', $signal))
            ->assertRedirect();

        $this->assertSame(ImprovementSignal::STATUS_DISMISSED, $signal->fresh()->status);
    }

    #[Test]
    public function pipeline_creates_hypotheses_with_fake_llm(): void
    {
        $this->fakeLlm();

        $lead = Lead::factory()->create([
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostPrice->value,
            'lost_reason' => 'Дорого относительно линии',
            'updated_at' => now(),
        ]);
        $this->leadIds[] = $lead->id;

        /** @var ImprovementHypothesisPipeline $pipeline */
        $pipeline = $this->app->make(ImprovementHypothesisPipeline::class);
        $result = $pipeline->run(30);

        $this->assertSame('success', $result['status']);
        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertTrue(
            ImprovementHypothesis::query()
                ->where('text', 'Сначала спрашивать про сроки поставки')
                ->where('status', 'draft')
                ->exists()
        );

        ImprovementHypothesis::query()
            ->where('text', 'Сначала спрашивать про сроки поставки')
            ->update(['source' => 'test_loop']);
    }

    #[Test]
    public function experiment_flow_adopt_creates_task(): void
    {
        $user = $this->makeReportsUser();
        $peer = User::factory()->create();
        $this->userIds[] = $peer->id;

        $hypothesis = ImprovementHypothesis::query()->create([
            'category' => 'script',
            'text' => 'TEST_LOOP_hypothesis_adopt',
            'short_reason' => 'Смещает фокус с цены',
            'impact' => 8,
            'confidence' => 7,
            'ease' => 3,
            'score' => 5.0,
            'status' => ImprovementHypothesis::STATUS_ACCEPTED,
            'source' => 'test_loop',
            'fingerprint' => hash('sha256', 'script|test_loop_hypothesis_adopt'),
        ]);

        $lead = Lead::factory()->create([
            'responsible_id' => $peer->id,
            'status' => 'won',
            'updated_at' => now(),
        ]);
        $this->leadIds[] = $lead->id;

        /** @var ImprovementExperimentService $experiments */
        $experiments = $this->app->make(ImprovementExperimentService::class);

        $experiment = $experiments->create($hypothesis, $user, [
            'name' => 'TEST_LOOP_exp',
            'assignment_mode' => ImprovementExperiment::ASSIGNMENT_MANAGERS,
            'starts_on' => now()->subDays(7)->toDateString(),
            'ends_on' => now()->toDateString(),
            'cohort' => [
                'variant_a_user_ids' => [$user->id],
                'variant_b_user_ids' => [$peer->id],
            ],
            'variant_a' => ['label' => 'A'],
            'variant_b' => ['label' => 'B'],
        ]);

        $experiments->start($experiment);
        $completed = $experiments->complete($experiment->fresh(), $user, [
            'verdict' => ImprovementExperiment::VERDICT_ADOPT_B,
            'verdict_note' => 'B лучше по отклику',
        ]);

        $this->assertSame(ImprovementExperiment::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->adoption);
        $this->assertSame(ImprovementHypothesis::STATUS_ADOPTED, $hypothesis->fresh()->status);

        if (Schema::hasTable('tasks') && $completed->adoption?->target_id) {
            $this->taskIds[] = (int) $completed->adoption->target_id;
            $this->assertDatabaseHas('tasks', [
                'id' => $completed->adoption->target_id,
            ]);
        }
    }

    #[Test]
    public function memory_skips_rejected_fingerprint(): void
    {
        $this->fakeLlm();

        ImprovementHypothesis::query()->create([
            'category' => 'script',
            'text' => 'Сначала спрашивать про сроки поставки',
            'status' => ImprovementHypothesis::STATUS_REJECTED,
            'source' => 'test_loop',
            'fingerprint' => hash('sha256', 'script|сначала спрашивать про сроки поставки'),
            'created_at' => now()->subDay(),
        ]);

        $lead = Lead::factory()->create([
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostPrice->value,
            'lost_reason' => 'Дорого',
            'updated_at' => now(),
        ]);
        $this->leadIds[] = $lead->id;

        $pipeline = $this->app->make(ImprovementHypothesisPipeline::class);
        $result = $pipeline->run(30);

        $this->assertTrue(in_array($result['status'], ['success', 'no_data'], true));
        $this->assertSame(
            1,
            ImprovementHypothesis::query()
                ->where('fingerprint', hash('sha256', 'script|сначала спрашивать про сроки поставки'))
                ->count()
        );
    }

    private function makeReportsUser(): User
    {
        $role = Role::query()->create([
            'name' => 'reports_mgr_'.uniqid(),
            'display_name' => 'Reports',
            'permissions' => [],
            'visibility_areas' => ['reports', 'leads'],
        ]);
        $this->roleIds[] = $role->id;

        $user = User::factory()->create(['role_id' => $role->id]);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function fakeLlm(): void
    {
        $calls = 0;
        $fake = new class($calls) implements ChatCompletionClient
        {
            public function __construct(private int &$calls) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                $this->calls++;

                return match ($this->calls) {
                    1 => json_encode(['pains' => ['дорого', 'нет отсрочки']], JSON_UNESCAPED_UNICODE),
                    2 => json_encode([
                        'ideas' => [
                            [
                                'category' => 'script',
                                'text' => 'Сначала спрашивать про сроки поставки',
                                'short_reason' => 'Смещает фокус',
                            ],
                            [
                                'category' => 'price',
                                'text' => 'Пакет базовый плюс скорость',
                                'short_reason' => 'Вариативность тарифа',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    3 => json_encode([
                        'ideas' => [
                            [
                                'category' => 'script',
                                'text' => 'Сначала спрашивать про сроки поставки',
                                'short_reason' => 'Смещает фокус',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    default => json_encode([
                        'hypotheses' => [
                            [
                                'category' => 'script',
                                'text' => 'Сначала спрашивать про сроки поставки',
                                'short_reason' => 'Смещает фокус',
                                'impact' => 8,
                                'confidence' => 7,
                                'ease' => 3,
                                'score' => 5.0,
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                };
            }
        };

        $this->app->instance(ChatCompletionClient::class, $fake);
    }
}
