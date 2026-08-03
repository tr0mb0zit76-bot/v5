<?php

namespace Tests\Feature\Improvement;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementExperimentAssignment;
use App\Models\ImprovementHypothesis;
use App\Models\ImprovementSignal;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\Improvement\ImprovementAbStatistics;
use App\Services\Improvement\ImprovementExperimentAssignmentService;
use App\Services\Improvement\ImprovementExperimentService;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImprovementLoopL4Test extends TestCase
{
    /** @var list<int> */
    private array $leadIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $roleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('improvement_experiment_assignments')) {
            $this->markTestSkipped('L4 tables missing — run migrations.');
        }
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('improvement_experiment_assignments')) {
            ImprovementExperimentAssignment::query()
                ->whereIn('lead_id', $this->leadIds ?: [0])
                ->delete();
        }

        if (Schema::hasTable('improvement_experiments')) {
            ImprovementExperiment::query()->where('name', 'like', 'TEST_L4_%')->delete();
        }

        if (Schema::hasTable('improvement_hypotheses')) {
            ImprovementHypothesis::query()->where('source', 'test_l4')->delete();
        }

        if (Schema::hasTable('improvement_signals')) {
            ImprovementSignal::query()->where('kind', 'experiment_outcome')->where('created_at', '>=', now()->subHour())->delete();
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
    public function ab_statistics_flags_significance_on_clear_lift(): void
    {
        $stats = new ImprovementAbStatistics;
        $result = $stats->compareWinRates(
            ['closed' => 80, 'won' => 16, 'lost' => 64, 'win_rate_pct' => 20.0],
            ['closed' => 80, 'won' => 40, 'lost' => 40, 'win_rate_pct' => 50.0],
            ImprovementAbStatistics::DEFAULT_ALPHA,
            25.0,
        );

        $this->assertTrue($result['significant']);
        $this->assertGreaterThan(0, $result['diff_pp']);
        $this->assertTrue($result['powered']);
        $this->assertSame('suggest_adopt_b', $result['recommendation']);
    }

    #[Test]
    public function lead_randomization_assigns_stable_variant_and_backfills(): void
    {
        $user = $this->makeReportsUser();
        $hypothesis = ImprovementHypothesis::query()->create([
            'category' => 'script',
            'text' => 'TEST_L4_hyp',
            'status' => ImprovementHypothesis::STATUS_ACCEPTED,
            'source' => 'test_l4',
            'score' => 4,
            'fingerprint' => hash('sha256', 'script|test_l4_hyp'),
        ]);

        /** @var ImprovementExperimentService $experiments */
        $experiments = $this->app->make(ImprovementExperimentService::class);
        $experiment = $experiments->create($hypothesis, $user, [
            'name' => 'TEST_L4_exp',
            'assignment_mode' => ImprovementExperiment::ASSIGNMENT_LEADS,
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'cohort' => ['pool_user_ids' => [$user->id]],
            'variant_a' => ['label' => 'A'],
            'variant_b' => ['label' => 'B'],
        ]);

        $leadWon = Lead::factory()->create([
            'responsible_id' => $user->id,
            'status' => 'won',
            'updated_at' => now()->subDay(),
        ]);
        $leadLost = Lead::factory()->create([
            'responsible_id' => $user->id,
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostPrice->value,
            'updated_at' => now()->subDay(),
        ]);
        $this->leadIds = [$leadWon->id, $leadLost->id];

        $experiments->start($experiment);

        $this->assertGreaterThanOrEqual(2, ImprovementExperimentAssignment::query()->where('experiment_id', $experiment->id)->count());

        /** @var ImprovementExperimentAssignmentService $assigner */
        $assigner = $this->app->make(ImprovementExperimentAssignmentService::class);
        $v1 = $assigner->variantForLead($experiment, $leadWon->id);
        $v2 = $assigner->variantForLead($experiment, $leadWon->id);
        $this->assertSame($v1, $v2);
        $this->assertContains($v1, ['a', 'b']);
    }

    #[Test]
    public function complete_writes_stats_and_next_cycle_signal(): void
    {
        $user = $this->makeReportsUser();
        $hypothesis = ImprovementHypothesis::query()->create([
            'category' => 'script',
            'text' => 'TEST_L4_hyp2',
            'status' => ImprovementHypothesis::STATUS_ACCEPTED,
            'source' => 'test_l4',
            'score' => 4,
            'fingerprint' => hash('sha256', 'script|test_l4_hyp2'),
        ]);

        /** @var ImprovementExperimentService $experiments */
        $experiments = $this->app->make(ImprovementExperimentService::class);
        $experiment = $experiments->create($hypothesis, $user, [
            'name' => 'TEST_L4_exp2',
            'assignment_mode' => ImprovementExperiment::ASSIGNMENT_LEADS,
            'starts_on' => now()->subDays(7)->toDateString(),
            'ends_on' => now()->toDateString(),
            'cohort' => ['pool_user_ids' => [$user->id]],
            'variant_a' => ['label' => 'A'],
            'variant_b' => ['label' => 'B'],
        ]);

        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'status' => 'won',
            'updated_at' => now()->subDay(),
        ]);
        $this->leadIds[] = $lead->id;

        $experiments->start($experiment);
        $completed = $experiments->complete($experiment->fresh(), $user, [
            'verdict' => ImprovementExperiment::VERDICT_INCONCLUSIVE,
            'verdict_note' => 'Мало данных, но цикл закрываем',
        ]);

        $this->assertArrayHasKey('stats', $completed->result_snapshot ?? []);
        $this->assertTrue(
            ImprovementSignal::query()
                ->where('kind', 'experiment_outcome')
                ->where('payload->experiment_id', $completed->id)
                ->exists()
        );
    }

    private function makeReportsUser(): User
    {
        $role = Role::query()->create([
            'name' => 'reports_l4_'.uniqid(),
            'display_name' => 'Reports L4',
            'permissions' => [],
            'visibility_areas' => ['reports', 'leads'],
        ]);
        $this->roleIds[] = $role->id;

        $user = User::factory()->create(['role_id' => $role->id]);
        $this->userIds[] = $user->id;

        return $user;
    }
}
