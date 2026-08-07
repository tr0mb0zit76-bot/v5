<?php

namespace Tests\Feature;

use App\Jobs\EnrichContractorJob;
use App\Models\Contractor;
use App\Models\ContractorEnrichmentRun;
use App\Models\ContractorInsightDraft;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContractorEnrichmentTest extends TestCase
{
    public function test_owner_can_run_enrichment_sync_and_get_internal_notes_draft(): void
    {
        Http::fake([
            'html.duckduckgo.com/*' => Http::response(
                '<a class="result__a" href="https://example.com/about">ООО Тест</a><a class="result__snippet">Логистика и поставки кормов</a>',
                200,
            ),
            'example.com/*' => Http::response('<html><body>Компания возит зерно</body></html>', 200),
        ]);

        [$owner, $contractor] = $this->makeOwnerContractor([
            'name' => 'ООО Агротест',
            'inn' => '7707083893',
            'website' => 'https://example.com',
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('contractors.enrichment.store', $contractor),
            ['async' => false, 'force' => true],
        );

        $response->assertOk();
        $response->assertJsonPath('queued', false);
        $response->assertJsonPath('run.status', ContractorEnrichmentRun::STATUS_SUCCEEDED);

        $this->assertDatabaseHas('contractor_enrichment_runs', [
            'contractor_id' => $contractor->id,
            'status' => ContractorEnrichmentRun::STATUS_SUCCEEDED,
            'trigger' => ContractorEnrichmentRun::TRIGGER_MANUAL,
        ]);

        $this->assertDatabaseHas('contractor_insight_drafts', [
            'contractor_id' => $contractor->id,
            'field_key' => 'internal_notes',
            'status' => ContractorInsightDraft::STATUS_PENDING,
        ]);
    }

    public function test_non_owner_cannot_run_enrichment(): void
    {
        [$owner, $contractor] = $this->makeOwnerContractor();
        $stranger = $this->makeUserWithContractorsArea('stranger-'.uniqid());

        $response = $this->actingAs($stranger)->postJson(
            route('contractors.enrichment.store', $contractor),
            ['async' => false, 'force' => true],
        );

        $response->assertForbidden();
    }

    public function test_show_enrichment_forbidden_without_visibility(): void
    {
        [$owner, $contractor] = $this->makeOwnerContractor();

        $role = Role::query()->create([
            'name' => 'no-contractors-'.uniqid(),
            'visibility_areas' => ['leads'],
        ]);
        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'NoArea',
            'email' => 'noarea-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('contractors.enrichment.show', $contractor),
        );

        $response->assertForbidden();
        unset($owner);
    }

    public function test_create_contractor_dispatches_enrichment_job(): void
    {
        Queue::fake();

        $owner = $this->makeUserWithContractorsArea('create-'.uniqid());

        $response = $this->actingAs($owner)->post(route('contractors.store'), [
            'type' => 'customer',
            'name' => 'ООО Create Enrich '.uniqid(),
            'inn' => '5408231999',
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
        ]);

        $response->assertRedirect();

        Queue::assertPushed(EnrichContractorJob::class);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array{0: User, 1: Contractor}
     */
    private function makeOwnerContractor(array $attrs = []): array
    {
        $owner = $this->makeUserWithContractorsArea('owner-'.uniqid());

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => $attrs['name'] ?? 'ООО Enrich',
            'inn' => $attrs['inn'] ?? null,
            'website' => $attrs['website'] ?? null,
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        return [$owner, $contractor];
    }

    private function makeUserWithContractorsArea(string $suffix): User
    {
        $role = Role::query()->create([
            'name' => 'mgr-'.$suffix,
            'visibility_areas' => ['contractors'],
        ]);

        return User::query()->create([
            'role_id' => $role->id,
            'name' => 'Manager '.$suffix,
            'email' => $suffix.'@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
