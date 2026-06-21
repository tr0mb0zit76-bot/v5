<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\Checko\ContractorRiskAssessmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorScoringV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractor_risk_assessments',
            'contractor_risk_snapshots',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->decimal('debt_limit', 12, 2)->nullable();
            $table->string('debt_limit_currency', 3)->default('RUB');
            $table->boolean('stop_on_limit')->default(false);
            $table->json('default_customer_payment_schedule')->nullable();
            $table->json('default_carrier_payment_schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_own_company')->default(false);
            $table->timestamps();
        });

        Schema::create('contractor_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('inn', 12);
            $table->string('model_version', 16);
            $table->json('normalized_data');
            $table->json('scoring_result');
            $table->boolean('checko_from_cache')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_risk_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_version', 16);
            $table->string('status', 16)->default('draft');
            $table->string('outcome', 32)->nullable();
            $table->unsignedTinyInteger('draft_score')->nullable();
            $table->string('draft_grade', 2)->nullable();
            $table->string('draft_tier', 16)->nullable();
            $table->unsignedInteger('draft_recommended_debt_limit_rub')->nullable();
            $table->unsignedTinyInteger('draft_recommended_postpayment_days')->nullable();
            $table->decimal('applied_debt_limit_rub', 14, 2)->nullable();
            $table->unsignedTinyInteger('applied_postpayment_days')->nullable();
            $table->string('applied_schedule_target', 16)->nullable();
            $table->json('edit_delta')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

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
