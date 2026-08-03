<?php

namespace Tests\Feature\Improvement;

use App\Enums\SalesScriptNodeKind;
use App\Models\ImprovementAdoption;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\ImprovementSignal;
use App\Models\Role;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Services\Improvement\ImprovementAdoptionService;
use App\Services\Improvement\ImprovementSignalCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImprovementLoopL5Test extends TestCase
{
    /** @var list<int> */
    private array $cleanupSignalIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $roleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('improvement_signals')) {
            $this->markTestSkipped('improvement tables missing');
        }
    }

    protected function tearDown(): void
    {
        if ($this->cleanupSignalIds !== []) {
            ImprovementSignal::query()->whereIn('id', $this->cleanupSignalIds)->delete();
        }

        ImprovementSignal::query()
            ->whereIn('domain', ['documents', 'fleet', 'finance'])
            ->where('created_at', '>=', now()->subHour())
            ->delete();

        ImprovementHypothesis::query()->where('source', 'test_l5')->delete();
        ImprovementExperiment::query()->where('name', 'like', 'TEST_L5_%')->delete();

        if ($this->userIds !== []) {
            User::query()->whereIn('id', $this->userIds)->forceDelete();
        }
        if ($this->roleIds !== []) {
            Role::query()->whereIn('id', $this->roleIds)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function collector_emits_finance_signal_when_pending_lines_exist(): void
    {
        if (! Schema::hasTable('management_statement_lines')) {
            $this->markTestSkipped('management_statement_lines missing');
        }

        $before = ImprovementSignal::query()->where('kind', 'mgmt_unallocated_lines')->count();

        // Ensure enough pending rows for threshold (>=5) without owning FK-heavy imports:
        $pending = (int) DB::table('management_statement_lines')->where('status', 'pending')->count();
        if ($pending < 5) {
            $this->markTestSkipped('Need >=5 pending management_statement_lines in test DB');
        }

        $collector = new ImprovementSignalCollector(
            new ManagerDealSignalExtractor(new ActivityLedgerService),
        );
        $created = $collector->collect(30, ['finance']);

        $this->assertNotEmpty($created);
        $this->assertTrue(
            ImprovementSignal::query()->where('domain', 'finance')->where('kind', 'mgmt_unallocated_lines')->exists()
        );
        $this->assertGreaterThanOrEqual($before, ImprovementSignal::query()->where('kind', 'mgmt_unallocated_lines')->count());
    }

    #[Test]
    public function apply_to_script_node_sets_variant_b_without_touching_body(): void
    {
        if (! Schema::hasTable('sales_script_nodes') || ! Schema::hasColumn('improvement_adoptions', 'meta')) {
            $this->markTestSkipped('script nodes / adoption meta missing');
        }

        $user = $this->makeReportsUser();
        $script = SalesScript::query()->create([
            'title' => 'TEST_L5_script',
            'description' => null,
            'channel' => 'phone',
            'tags' => [],
        ]);
        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $node = SalesScriptNode::query()->create([
            'sales_script_version_id' => $version->id,
            'client_key' => 'n1',
            'kind' => SalesScriptNodeKind::Say,
            'body' => 'Оригинальный текст',
            'sort_order' => 1,
            'canvas_x' => 0,
            'canvas_y' => 0,
        ]);

        $hypothesis = ImprovementHypothesis::query()->create([
            'category' => 'script',
            'text' => 'TEST_L5 сначала спросить про сроки',
            'status' => ImprovementHypothesis::STATUS_ADOPTED,
            'source' => 'test_l5',
            'fingerprint' => hash('sha256', 'script|test_l5'),
        ]);

        $experiment = ImprovementExperiment::query()->create([
            'hypothesis_id' => $hypothesis->id,
            'name' => 'TEST_L5_exp',
            'status' => ImprovementExperiment::STATUS_COMPLETED,
            'variant_a' => ['label' => 'A'],
            'variant_b' => ['label' => 'B'],
            'metric_key' => 'win_rate',
            'assignment_mode' => 'managers',
            'verdict' => ImprovementExperiment::VERDICT_ADOPT_B,
            'verdict_note' => 'ok',
            'created_by' => $user->id,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        $adoption = ImprovementAdoption::query()->create([
            'experiment_id' => $experiment->id,
            'hypothesis_id' => $hypothesis->id,
            'target_type' => ImprovementAdoption::TARGET_MANUAL_NOTE,
            'summary' => 'test',
            'adopted_by' => $user->id,
            'adopted_at' => now(),
            'meta' => [
                'proposed_body_variant_b' => $hypothesis->text,
                'sales_script_version_id' => $version->id,
                'script_applied' => false,
            ],
        ]);

        /** @var ImprovementAdoptionService $service */
        $service = $this->app->make(ImprovementAdoptionService::class);
        $service->applyToScriptNode($adoption, $node, $user);

        $node->refresh();
        $this->assertSame('Оригинальный текст', $node->body);
        $this->assertSame($hypothesis->text, $node->body_variant_b);
        $this->assertTrue((bool) $node->ab_enabled);
        $this->assertTrue((bool) ($adoption->fresh()->meta['script_applied'] ?? false));

        // cleanup script fixtures
        $node->delete();
        $version->delete();
        $script->delete();
        $adoption->delete();
        $experiment->delete();
        $hypothesis->delete();
    }

    private function makeReportsUser(): User
    {
        $role = Role::query()->create([
            'name' => 'reports_l5_'.uniqid(),
            'display_name' => 'Reports L5',
            'permissions' => [],
            'visibility_areas' => ['reports', 'leads'],
        ]);
        $this->roleIds[] = $role->id;
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->userIds[] = $user->id;

        return $user;
    }
}
