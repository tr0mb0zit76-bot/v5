<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\Checko\ContractorRiskAssessmentService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorScoringV2Test extends TestCase
{
    #[Test]
    public function confirm_accepted_as_is_applies_draft_and_marks_verified(): void
    {
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
        ]);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '7707083893',
            'debt_limit' => 100_000,
            'default_customer_payment_schedule' => ['postpayment_days' => 0, 'postpayment_mode' => 'ottn'],
        ]);

        $assessment = ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'model_version' => '2.0',
            'status' => ContractorRiskAssessment::STATUS_DRAFT,
            'draft_score' => 80,
            'draft_grade' => 'A',
            'draft_tier' => 'small',
            'draft_recommended_debt_limit_rub' => 500_000,
            'draft_recommended_postpayment_days' => 7,
        ]);

        $service = app(ContractorRiskAssessmentService::class);
        $result = $service->confirm(
            $contractor,
            $assessment,
            $user,
            ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS,
            0,
            0,
            'customer',
        );

        $contractor->refresh();
        $assessment->refresh();

        $this->assertSame(ContractorRiskAssessment::STATUS_APPROVED, $assessment->status);
        $this->assertSame(ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS, $assessment->outcome);
        $this->assertSame('500000.00', $contractor->debt_limit);
        $this->assertSame(7, (int) data_get($contractor->default_customer_payment_schedule, 'postpayment_days'));
        $this->assertTrue((bool) $contractor->is_verified);
        $this->assertTrue($result['verification']['is_verified']);
    }

    #[Test]
    public function confirm_endpoint_returns_json_for_authenticated_user(): void
    {
        $admin = $this->createAdminUser();

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент 2',
            'inn' => '7707083893',
            'debt_limit' => 100_000,
            'default_customer_payment_schedule' => ['postpayment_days' => 0, 'postpayment_mode' => 'ottn'],
        ]);

        $assessment = ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'model_version' => '2.0',
            'status' => ContractorRiskAssessment::STATUS_DRAFT,
            'draft_recommended_debt_limit_rub' => 300_000,
            'draft_recommended_postpayment_days' => 5,
        ]);

        $response = $this->actingAs($admin)->postJson(route('contractors.risk-assessment.confirm', $contractor), [
            'assessment_id' => $assessment->id,
            'outcome' => ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS,
            'schedule_target' => 'customer',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('outcome', ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS);
        $response->assertJsonPath('verification.is_verified', true);
    }

    #[Test]
    public function confirm_with_edits_applies_custom_limit_and_postpayment(): void
    {
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-edits@example.com',
            'password' => bcrypt('password'),
        ]);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент 3',
            'inn' => '7707083894',
            'debt_limit' => 100_000,
            'default_customer_payment_schedule' => ['postpayment_days' => 0, 'postpayment_mode' => 'ottn'],
        ]);

        $assessment = ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'model_version' => '2.0',
            'status' => ContractorRiskAssessment::STATUS_DRAFT,
            'draft_recommended_debt_limit_rub' => 300_000,
            'draft_recommended_postpayment_days' => 5,
        ]);

        $service = app(ContractorRiskAssessmentService::class);
        $service->confirm(
            $contractor,
            $assessment,
            $user,
            ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS,
            250_000.0,
            14,
            'customer',
        );

        $contractor->refresh();
        $assessment->refresh();

        $this->assertSame(ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS, $assessment->outcome);
        $this->assertSame('250000.00', $contractor->debt_limit);
        $this->assertSame(14, (int) data_get($contractor->default_customer_payment_schedule, 'postpayment_days'));
    }

    #[Test]
    public function confirm_with_edits_accepts_empty_debt_limit_via_http(): void
    {
        $admin = $this->createAdminUser();

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент 4',
            'inn' => '7707083895',
            'debt_limit' => 100_000,
            'default_customer_payment_schedule' => ['postpayment_days' => 0, 'postpayment_mode' => 'ottn'],
        ]);

        $assessment = ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'model_version' => '2.0',
            'status' => ContractorRiskAssessment::STATUS_DRAFT,
            'draft_recommended_debt_limit_rub' => 300_000,
            'draft_recommended_postpayment_days' => 5,
        ]);

        $response = $this->actingAs($admin)->postJson(route('contractors.risk-assessment.confirm', $contractor), [
            'assessment_id' => $assessment->id,
            'outcome' => ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS,
            'applied_debt_limit' => '',
            'applied_postpayment_days' => 14,
            'schedule_target' => 'customer',
        ]);

        $response->assertOk();
        $response->assertJsonPath('outcome', ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS);
    }

    private function createAdminUser(): User
    {
        $adminRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $adminRoleId,
        ]);
    }
}
