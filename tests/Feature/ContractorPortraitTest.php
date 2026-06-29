<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\ContractorPortrait;
use App\Models\User;
use App\Services\Contractor\ContractorPortraitCoverage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractorPortraitTest extends TestCase
{
    public function test_portrait_update_recalculates_coverage(): void
    {
        $user = $this->makeAdminUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Портрет',
            'owner_id' => $user->id,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Иванов',
            'role_in_deal' => 'decision_maker',
            'is_decision_maker' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('contractors.portrait.update', $contractor), [
            'communication_style' => 'analytical',
            'preferred_channel' => 'phone',
            'success_criteria' => 'Доставить в срок без срывов',
            'typical_objections' => ['price'],
        ]);

        $response->assertRedirect(route('contractors.show', ['contractor' => $contractor->id, 'tab' => 'portrait']));

        $portrait = ContractorPortrait::query()->findOrFail($contractor->id);
        $this->assertSame('analytical', $portrait->communication_style);
        $this->assertGreaterThanOrEqual(55, $portrait->coverage_pct);
    }

    public function test_interaction_merge_updates_portrait_objections(): void
    {
        $user = $this->makeAdminUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Контакт',
            'owner_id' => $user->id,
        ]);

        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Петров',
            'role_in_deal' => 'decision_maker',
        ]);

        $response = $this->actingAs($user)->postJson(route('contractors.portrait-interactions.store', $contractor), [
            'contractor_contact_id' => $contact->id,
            'contacted_at' => now()->toIso8601String(),
            'channel' => 'phone',
            'outcome_code' => 'objection',
            'summary' => 'Клиент считает ставку высокой',
            'objection_tags' => ['price'],
            'merge_to_portrait' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('portrait.typical_objections', ['price']);
        $this->assertGreaterThanOrEqual(
            ContractorPortraitCoverage::ASSISTANT_THRESHOLD,
            (int) $response->json('portrait.coverage_pct'),
        );
    }

    private function makeAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['contractors', 'leads', 'orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Admin',
            'email' => 'portrait-admin@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
