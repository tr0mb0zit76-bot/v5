<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorPortrait;
use App\Models\Lead;
use App\Models\User;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadPortraitMergeTest extends TestCase
{
    public function test_merge_from_lead_qualification_updates_portrait(): void
    {
        $user = $this->makeLeadsUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Лид',
            'owner_id' => $user->id,
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-1',
            'status' => 'new',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
            'title' => 'Тест',
            'lead_qualification' => [
                'need' => 'Доставка без срывов',
                'authority' => 'Иванов, директор',
                'budget' => 'Жмут по бюджету',
                'timeline' => 'Срочно до пятницы',
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('leads.portrait-merge', $lead), [
            'qualification' => $lead->lead_qualification,
        ]);

        $response->assertOk();
        $response->assertJsonPath('portrait.success_criteria', 'Доставка без срывов');
        $response->assertJsonPath('portrait.price_sensitivity', 'high');
        $response->assertJsonPath('portrait.decision_cadence', 'fast');

        $portrait = ContractorPortrait::query()->findOrFail($contractor->id);
        $this->assertStringContainsString('Иванов', (string) $portrait->internal_notes);
        $this->assertStringContainsString('Срочно до пятницы', (string) $portrait->internal_notes);
    }

    public function test_preview_skips_already_filled_success_criteria(): void
    {
        $user = $this->makeLeadsUser();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заполнено',
            'owner_id' => $user->id,
        ]);

        ContractorPortrait::query()->create([
            'contractor_id' => $contractor->id,
            'communication_style' => ContractorPortraitDictionary::UNKNOWN,
            'price_sensitivity' => ContractorPortraitDictionary::UNKNOWN,
            'preferred_channel' => ContractorPortraitDictionary::UNKNOWN,
            'decision_cadence' => ContractorPortraitDictionary::UNKNOWN,
            'relationship_trust' => ContractorPortraitDictionary::UNKNOWN,
            'success_criteria' => 'Уже известно',
            'coverage_pct' => 20,
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-2',
            'status' => 'new',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
            'title' => 'Тест заполненного портрета',
            'lead_qualification' => ['need' => 'Новая потребность'],
        ]);

        $response = $this->actingAs($user)->getJson(route('leads.portrait-merge.preview', $lead));

        $response->assertOk();
        $response->assertJsonFragment(['Потребность — критерии успеха уже заполнены']);
        $this->assertSame([], $response->json('proposed'));
    }

    private function makeLeadsUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['leads', 'contractors']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Manager',
            'email' => 'manager-lead-portrait@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
